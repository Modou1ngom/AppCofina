<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\MissionLog;
use App\Models\MissionParticipant;
use App\Models\MissionRapportPieceJointe;
use App\Models\Profil;
use App\Models\User;
use App\Support\MissionRapport;
use App\Support\MissionSites;
use App\Services\MissionNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MissionController extends Controller
{
  private const ACTIONS_VALIDATION_VALIDATEUR = [
        'approbation',
        'attribution_facilities',
        'generation_ordre_mission',
        'rejet',
        'renvoi',
    ];

    /**
     * @return array<int, int>
     */
    private function idsMissionsValideesParUtilisateur(User $user): array
    {
        return MissionLog::query()
            ->where('user_id', $user->id)
            ->whereIn('action', self::ACTIONS_VALIDATION_VALIDATEUR)
            ->distinct()
            ->pluck('mission_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function utilisateurAValideMission(User $user, Mission $mission): bool
    {
        return MissionLog::query()
            ->where('mission_id', $mission->id)
            ->where('user_id', $user->id)
            ->whereIn('action', self::ACTIONS_VALIDATION_VALIDATEUR)
            ->exists();
    }

    /**
     * @param  array<int, string>  $etapesEnCours
     * @param  (callable(\Illuminate\Database\Eloquent\Builder): void)|null  $filtreEnCours
     */
    private function contraindreMissionsFileValidation($query, User $user, array $etapesEnCours, ?callable $filtreEnCours = null): void
    {
        $query->where(function ($qEnCours) use ($etapesEnCours, $filtreEnCours) {
            $qEnCours->whereIn('current_step', $etapesEnCours);
            if ($filtreEnCours !== null) {
                $filtreEnCours($qEnCours);
            }
        });
    }

    /**
     * Missions validées par l'utilisateur qui ne sont plus à son étape (hors renvois actifs).
     *
     * @return array<int, int>
     */
    private function idsMissionsHistoriqueValideesParUtilisateur(User $user): array
    {
        $ids = $this->idsMissionsValideesParUtilisateur($user);
        if ($ids === []) {
            return [];
        }

        return Mission::query()
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn (Mission $mission) => ! $this->peutTraiterEtapeCourante($user, $mission))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Mission>  $query
     */
    private function appliquerFiltreNumeroMission($query, Request $request): void
    {
        $filtreNumero = trim((string) $request->query('numero', ''));
        if ($filtreNumero !== '' && ctype_digit($filtreNumero)) {
            $query->where('numero_mission', (int) $filtreNumero);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Mission>  $query
     */
    private function appliquerFiltreDemandeurMission($query, Request $request): void
    {
        $filtreDemandeur = trim((string) $request->query('demandeur', ''));
        if ($filtreDemandeur === '') {
            return;
        }

        $query->whereHas('demandeur', function ($q) use ($filtreDemandeur) {
            $q->where('name', 'like', '%'.$filtreDemandeur.'%');
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Mission>  $query
     */
    private function appliquerFiltresTableauDeBord($query, Request $request): void
    {
        $this->appliquerFiltreNumeroMission($query, $request);
        $this->appliquerFiltreDemandeurMission($query, $request);
    }

    private function filtreDemandeurMissionPourVue(Request $request): string
    {
        return trim((string) $request->query('demandeur', ''));
    }

    private function attribuerNumeroMissionSiNecessaire(Mission $mission): void
    {
        $mission->refresh();

        if ($mission->numero_mission !== null) {
            return;
        }

        if ($mission->status === 'brouillon' || $mission->current_step === Mission::STEP_BROUILLON) {
            return;
        }

        $numero = DB::transaction(function () {
            $max = Mission::query()
                ->whereNotNull('numero_mission')
                ->lockForUpdate()
                ->max('numero_mission');

            return (int) ($max ?? 0) + 1;
        });

        $mission->update(['numero_mission' => $numero]);
    }

    private function numeroMissionPourAffichage(Mission $mission): ?int
    {
        return $mission->numero_mission !== null ? (int) $mission->numero_mission : null;
    }

    private function filtreNumeroMissionPourVue(Request $request): string
    {
        $filtreNumero = trim((string) $request->query('numero', ''));

        return $filtreNumero !== '' && ctype_digit($filtreNumero) ? $filtreNumero : '';
    }

    private function appliquerFiltreBrouillonsDemandeur($query, User $user): void
    {
        $query->where(function ($q) use ($user) {
            $q->where(function ($inner) {
                $inner->where('status', '!=', 'brouillon')
                    ->where('current_step', '!=', Mission::STEP_BROUILLON);
            })->orWhere('demandeur_id', $user->id);
        });
    }

    private function utilisateurAVisibiliteMissionsComplete(User $user): bool
    {
        return $user->isAudit();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Mission>  $query
     */
    private function appliquerFiltreMissionsVisiblesUtilisateur($query, User $user, bool $inclureMissionsValidees = true): void
    {
        $this->appliquerFiltreBrouillonsDemandeur($query, $user);

        if ($this->utilisateurAVisibiliteMissionsComplete($user)) {
            return;
        }

        $profilId = $this->trouverProfilPourUser($user)?->id;
        $idsValidees = $inclureMissionsValidees ? $this->idsMissionsValideesParUtilisateur($user) : [];
        $etapesAvantFinance = $this->etapesAvantValidationFinanceLogistique();

        $query->where(function ($inner) use ($user, $profilId, $idsValidees, $etapesAvantFinance) {
            $inner->where('demandeur_id', $user->id)
                ->orWhere(function ($q) use ($user) {
                    $q->where('status', '!=', 'brouillon')
                        ->where('current_step', '!=', Mission::STEP_BROUILLON)
                        ->whereHas('participants', fn ($pq) => $pq->where('users.id', $user->id));
                });
            if ($profilId) {
                $inner->orWhere(function ($q) use ($profilId) {
                    $q->where('status', '!=', 'brouillon')
                        ->where('current_step', '!=', Mission::STEP_BROUILLON)
                        ->whereHas(
                            'missionParticipants',
                            fn ($mq) => $mq->where('profil_id', $profilId)->where('role_dans_mission', 'missionnaire'),
                        );
                });
            }
            if ($user->isMd()) {
                $inner->orWhere(function ($q) {
                    $q->where('current_step', Mission::STEP_ATTENTE_MD)
                        ->where('status', 'en_cours');
                });
            }
            if ($user->isDga()) {
                $inner->orWhere(function ($q) use ($user) {
                    $this->contraindreMissionsEnAttenteValidationDga($q, $user);
                });
            }
            $inner->orWhere(function ($q) use ($user, $profilId) {
                $q->where('current_step', Mission::STEP_ATTENTE_N1)
                    ->where('status', 'en_cours')
                    ->where(function ($n1) use ($user, $profilId) {
                        $n1->where('n2_beneficiaire_id', $user->id)
                            ->orWhereHas('demandeur', fn ($dq) => $dq->where('manager_id', $user->id));
                        if ($profilId !== null) {
                            $n1->orWhereHas('demandeur', function ($dq) use ($profilId) {
                                $dq->whereHas('profil', fn ($pq) => $pq->where('n_plus_1_id', $profilId));
                            });
                        }
                    });
            });
            if ($user->isLogistique()) {
                $inner->orWhere(function ($q) {
                    $q->where('current_step', Mission::STEP_ATTENTE_FACILITIES)
                        ->where('status', 'en_cours');
                });
            }
            if ($this->peutSignerRrh($user)) {
                $inner->orWhere(function ($q) {
                    $q->where('current_step', Mission::STEP_ATTENTE_SIGNATURE_RRH)
                        ->where('status', 'en_cours');
                });
            }
            if ($this->peutValiderRh($user)) {
                $inner->orWhere(function ($q) {
                    $q->whereIn('current_step', [Mission::STEP_ATTENTE_RH, 'ATTENTE_RH_LOGISTIQUE'])
                        ->where('status', 'en_cours');
                });
            }
            if ($user->isFinance()) {
                $inner->orWhere(function ($q) use ($etapesAvantFinance, $user) {
                    $q->whereNotNull('total_logistique')
                        ->where('total_logistique', '>', 0)
                        ->whereNotIn('current_step', $etapesAvantFinance)
                        ->where(function ($finance) use ($user) {
                            $finance->whereNull('finance_logistique_validee_at')
                                ->orWhere('finance_logistique_validee_par', $user->id);
                        });
                });
            }
            if ($idsValidees !== []) {
                $inner->orWhereIn('id', $idsValidees);
            }
        });
    }

    private function missionVisiblePourUtilisateur(User $user, Mission $mission, bool $inclureHistoriqueValidees = true): bool
    {
        if ($this->utilisateurAVisibiliteMissionsComplete($user)) {
            return true;
        }

        if ($mission->status === 'brouillon' || $mission->current_step === Mission::STEP_BROUILLON) {
            return $this->estDemandeur($user, $mission);
        }

        if ($this->estDemandeur($user, $mission)) {
            return true;
        }

        if ($this->estParticipant($user, $mission)) {
            return true;
        }

        $profilId = $this->trouverProfilPourUser($user)?->id;
        if ($profilId !== null) {
            $mission->loadMissing('missionParticipants');
            $estMissionnaireProfil = $mission->missionParticipants
                ->where('profil_id', $profilId)
                ->where('role_dans_mission', 'missionnaire')
                ->isNotEmpty();
            if ($estMissionnaireProfil) {
                return true;
            }
        }

        if ($this->peutTraiterEtapeCourante($user, $mission)) {
            return true;
        }

        if ($user->isDga() && $mission->status === 'en_cours') {
            $eligibleDga = Mission::query()
                ->whereKey($mission->id)
                ->where(function ($q) use ($user) {
                    $this->contraindreMissionsEnAttenteValidationDga($q, $user);
                })
                ->exists();
            if ($eligibleDga) {
                return true;
            }
        }

        if ($this->peutValiderLogistiqueFinance($user, $mission)) {
            return true;
        }

        if ($user->isFinance()
            && $mission->finance_logistique_validee_at !== null
            && (int) $mission->finance_logistique_validee_par === $user->id) {
            return true;
        }

        if ($user->isResponsableRh()
            && in_array($mission->current_step, [Mission::STEP_ATTENTE_SIGNATURE_RRH, Mission::STEP_VALIDEE], true)) {
            return true;
        }

        if ($this->peutValiderRh($user) && $mission->current_step === Mission::STEP_ATTENTE_SIGNATURE_RRH) {
            return true;
        }

        if ($inclureHistoriqueValidees && $this->utilisateurAValideMission($user, $mission)) {
            return true;
        }

        return false;
    }

    private function determinerEtatListeValidation(Mission $mission, User $user): string
    {
        if ($this->peutTraiterEtapeCourante($user, $mission)) {
            $dernierLog = $mission->relationLoaded('logs')
                ? $mission->logs->sortByDesc('created_at')->first()
                : MissionLog::query()->where('mission_id', $mission->id)->latest()->first();

            if ($dernierLog?->action === 'renvoi') {
                return 'renvoyee';
            }

            return 'a_traiter';
        }

        if ($mission->current_step === Mission::STEP_CLOTUREE) {
            return 'cloturee';
        }

        return 'traitee';
    }

    private function appliquerEnrichissementListeValidation($paginator, User $user): void
    {
        $paginator->getCollection()->transform(function (Mission $mission) use ($user) {
            $mission->setAttribute('validation_etat', $this->determinerEtatListeValidation($mission, $user));
            $mission->setAttribute('etape_libelle', $this->libelleEtapeMission($mission));

            return $mission;
        });
    }

    private function peutTraiterEtapeCourante(User $user, Mission $mission): bool
    {
        return match ($mission->current_step) {
            Mission::STEP_ATTENTE_N1 => $this->estN1Validateur($user, $mission),
            Mission::STEP_ATTENTE_DGA => $user->isDga(),
            Mission::STEP_ATTENTE_MD => $user->isMd(),
            Mission::STEP_ATTENTE_RH, 'ATTENTE_RH_LOGISTIQUE' => $this->peutValiderRh($user),
            Mission::STEP_ATTENTE_FACILITIES => $user->isLogistique(),
            Mission::STEP_ATTENTE_SIGNATURE_RRH => $user->isResponsableRh(),
            Mission::STEP_ATTENTE_RAPPORT => $this->estMissionnaire($user, $mission),
            Mission::STEP_ATTENTE_VALIDATION_RAPPORT => $this->estDemandeur($user, $mission),
            default => false,
        };
    }

    private function notifierEtapeCourante(Mission $mission, ?string $messageParticipants = null): void
    {
        $mission->loadMissing('demandeur');
        $etapeLabel = $this->libelleEtapeMission($mission);

        MissionNotificationService::notifyDemandeurChangementEtape($mission, $etapeLabel);
        MissionNotificationService::notifyResponsablesEtapeCourante($mission, $etapeLabel);

        if ($messageParticipants !== null) {
            $this->notifierParticipants($mission, $messageParticipants);
        }

        $mission->update(['last_reminder_at' => null]);
    }

    private function peutValiderRh(User $user): bool
    {
        return $user->isRh();
    }

    private function peutSignerRrh(User $user): bool
    {
        return $user->isResponsableRh();
    }

    private function estDemandeur(User $user, Mission $mission): bool
    {
        return $mission->demandeur_id === $user->id;
    }

    private function demandeurPeutModifierDemande(User $user, Mission $mission): bool
    {
        return $this->estDemandeur($user, $mission)
            && in_array($mission->status, ['brouillon', 'renvoye'], true);
    }

    private function estN1Validateur(User $user, Mission $mission): bool
    {
        if ($mission->n2_beneficiaire_id === $user->id) {
            return true;
        }

        $mission->loadMissing('demandeur');
        $demandeur = $mission->demandeur;

        if ($demandeur === null) {
            return false;
        }

        if ($demandeur->manager_id === $user->id) {
            return true;
        }

        $profilValidateur = $this->trouverProfilPourUser($user);
        $profilDemandeur = $this->trouverProfilPourUser($demandeur);

        if ($profilValidateur !== null && $profilDemandeur?->n_plus_1_id === $profilValidateur->id) {
            return true;
        }

        return $this->resoudreN1ValidateurId($demandeur) === $user->id;
    }

    private function n1PeutModifierDemande(User $user, Mission $mission): bool
    {
        return $mission->current_step === Mission::STEP_ATTENTE_N1
            && $this->estN1Validateur($user, $mission)
            && ! $this->estDemandeur($user, $mission)
            && ! $user->isDga();
    }

    private function demandeurEffectifPourMission(User $user, Mission $mission): User
    {
        $mission->loadMissing('demandeur');

        return $this->estDemandeur($user, $mission)
            ? $user
            : ($mission->demandeur ?? $user);
    }

    private function resoudreN1ValidateurId(User $demandeur): ?int
    {
        if ($demandeur->manager_id) {
            return (int) $demandeur->manager_id;
        }

        $profil = $this->trouverProfilPourUser($demandeur);
        if ($profil === null || $profil->n_plus_1_id === null) {
            return null;
        }

        $n1Profil = Profil::query()->find($profil->n_plus_1_id);
        if ($n1Profil === null) {
            return null;
        }

        return $this->trouverUserPourProfil($n1Profil)?->id;
    }

    /**
     * Missions en attente de validation DGA (N+1 combiné et/ou étape DGA).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Mission>  $query
     */
    private function contraindreMissionsEnAttenteValidationDga($query, User $user): void
    {
        $dgaProfilId = $this->trouverProfilPourUser($user)?->id;

        $query->where('status', 'en_cours')
            ->where(function ($q) use ($user, $dgaProfilId) {
                $q->where(function ($q2) use ($user, $dgaProfilId) {
                    $q2->where('current_step', Mission::STEP_ATTENTE_N1)
                        ->where(function ($q3) use ($user, $dgaProfilId) {
                            $q3->where('n2_beneficiaire_id', $user->id);
                            if ($dgaProfilId !== null) {
                                $q3->orWhereHas('demandeur', function ($dq) use ($dgaProfilId) {
                                    $dq->whereHas('profil', fn ($pq) => $pq->where('n_plus_1_id', $dgaProfilId));
                                });
                            }
                        });
                })->orWhere('current_step', Mission::STEP_ATTENTE_DGA);
            });
    }

    /**
     * DGA valide N+1 et DGA en une seule signature (DGA = N+1 du demandeur, ou DGA = demandeur).
     */
    private function dgaValideN1EtDgaCombine(Mission $mission, User $user): bool
    {
        if (! $user->isDga()) {
            return false;
        }

        if ($mission->current_step === Mission::STEP_ATTENTE_N1 && $this->estN1Validateur($user, $mission)) {
            return true;
        }

        return $mission->current_step === Mission::STEP_ATTENTE_DGA
            && $this->estDemandeur($user, $mission);
    }

    private function missionAValidationN1DgaCombinee(Mission $mission): bool
    {
        if ($mission->dga_contournee) {
            return true;
        }

        return $mission->logs->contains(
            fn (MissionLog $l) => str_contains($l->commentaire ?? '', 'N+1 et DGA'),
        );
    }

    /**
     * @return array{n1: array, dga: array, md: array}
     */
    private function signaturesPourFicheValidation(Mission $mission): array
    {
        $defaut = ['nom' => '—', 'date' => '—', 'image' => null];

        $n1 = $this->signatureDepuisLogs($mission, 'Validation N+1') ?? $defaut;
        $dga = $this->signatureDepuisLogs($mission, 'Validation DGA') ?? $defaut;
        $md = $this->signatureDepuisLogs($mission, 'Validation DG (MD)') ?? $defaut;

        if ($this->missionAValidationN1DgaCombinee($mission)) {
            $combinee = ! empty($dga['image']) ? $dga : (! empty($n1['image']) ? $n1 : null);
            if ($combinee !== null) {
                $n1 = $dga = $combinee;
            }
        }

        return ['n1' => $n1, 'dga' => $dga, 'md' => $md];
    }

    private function estMissionnaire(User $user, Mission $mission): bool
    {
        $profil = $this->trouverProfilPourUser($user);

        return MissionParticipant::query()
            ->where('mission_id', $mission->id)
            ->where('role_dans_mission', 'missionnaire')
            ->where(function ($q) use ($user, $profil) {
                $q->where('user_id', $user->id);
                if ($profil !== null) {
                    $q->orWhere('profil_id', $profil->id);
                }
            })
            ->exists();
    }

    private function estParticipant(User $user, Mission $mission): bool
    {
        if ($mission->participants()->where('users.id', $user->id)->exists()) {
            return true;
        }

        $profil = $this->trouverProfilPourUser($user);
        if ($profil === null) {
            return false;
        }

        return MissionParticipant::query()
            ->where('mission_id', $mission->id)
            ->where('profil_id', $profil->id)
            ->where('role_dans_mission', 'missionnaire')
            ->exists();
    }

    /**
     * @return \Illuminate\Support\Collection<int, MissionParticipant>
     */
    private function missionnairesMission(Mission $mission): \Illuminate\Support\Collection
    {
        return MissionParticipant::query()
            ->with(['profil', 'user', 'chauffeur', 'chauffeurProfil'])
            ->where('mission_id', $mission->id)
            ->where('role_dans_mission', 'missionnaire')
            ->orderBy('id')
            ->get();
    }

    private function libelleEtapeMission(Mission $mission): string
    {
        return match ($mission->current_step) {
            Mission::STEP_BROUILLON => 'Brouillon',
            Mission::STEP_ATTENTE_N1 => 'En attente de validation N+1',
            Mission::STEP_ATTENTE_DGA => 'En attente de validation DGA',
            Mission::STEP_ATTENTE_MD => 'En attente de signature DG',
            Mission::STEP_ATTENTE_FACILITIES => 'En attente de traitement Facilities',
            Mission::STEP_ATTENTE_RH, 'ATTENTE_RH_LOGISTIQUE' => 'En attente de validation RH',
            Mission::STEP_ATTENTE_SIGNATURE_RRH => 'En attente de signature Responsable RH',
            Mission::STEP_VALIDEE => 'Validée — ordres de mission signés',
            Mission::STEP_ATTENTE_RAPPORT => 'En attente du rapport de mission signé',
            Mission::STEP_ATTENTE_VALIDATION_RAPPORT => 'En attente de validation du rapport par le demandeur',
            Mission::STEP_CLOTUREE => 'Mission clôturée officiellement',
            default => $mission->current_step,
        };
    }

    private function etapesApresFacilities(): array
    {
        return [
            Mission::STEP_ATTENTE_RH,
            'ATTENTE_RH_LOGISTIQUE',
            Mission::STEP_ATTENTE_SIGNATURE_RRH,
            Mission::STEP_VALIDEE,
            Mission::STEP_ATTENTE_RAPPORT,
            Mission::STEP_ATTENTE_VALIDATION_RAPPORT,
            Mission::STEP_CLOTUREE,
        ];
    }

    private function peutModifierDureeMission(User $user, Mission $mission): bool
    {
        if (! $this->estDemandeur($user, $mission)) {
            return false;
        }

        if (in_array($mission->current_step, [Mission::STEP_BROUILLON, Mission::STEP_CLOTUREE], true)) {
            return false;
        }

        if ($mission->status === 'rejete') {
            return false;
        }

        if ($this->missionnairesMission($mission)->isEmpty()) {
            return false;
        }

        return in_array($mission->current_step, $this->etapesApresFacilities(), true)
            || $mission->current_step === Mission::STEP_ATTENTE_FACILITIES;
    }

    private function reinitialiserLogistiqueMissionParticipant(MissionParticipant $participant): void
    {
        $participant->update([
            'vehicule' => null,
            'logement' => null,
            'per_diem' => 0,
            'prix_carburant' => 0,
            'prix_transport' => 0,
            'prix_logement' => 0,
            'autres_frais' => 0,
            'besoin_chauffeur' => false,
            'chauffeur_id' => null,
            'chauffeur_profil_id' => null,
        ]);
    }

    /**
     * @param  array<int, int>  $missionnaireIdsSelectionnes
     * @return array{conserves: array<int, string>, retires: array<int, string>}
     */
    private function appliquerSelectionMissionnairesPourModificationDuree(Mission $mission, array $missionnaireIdsSelectionnes): array
    {
        $missionnaires = $this->missionnairesMission($mission);
        $idsSelectionnes = collect($missionnaireIdsSelectionnes)->map(fn ($id) => (int) $id)->unique()->values();

        $aRetirer = $missionnaires->filter(fn (MissionParticipant $p) => ! $idsSelectionnes->contains($p->id));
        $aConserver = $missionnaires->filter(fn (MissionParticipant $p) => $idsSelectionnes->contains($p->id));

        foreach ($aRetirer as $participant) {
            $participant->delete();
        }

        foreach ($aConserver as $participant) {
            $this->archiverLogistiqueSitesParticipant($participant);
        }

        return [
            'conserves' => $aConserver->map(fn (MissionParticipant $p) => $p->nomAffichage())->values()->all(),
            'retires' => $aRetirer->map(fn (MissionParticipant $p) => $p->nomAffichage())->values()->all(),
        ];
    }

    private function estEtapeRh(Mission $mission): bool
    {
        return in_array($mission->current_step, [Mission::STEP_ATTENTE_RH, 'ATTENTE_RH_LOGISTIQUE'], true);
    }

    /**
     * @return array<int, string>
     */
    private function etapesAvantRh(): array
    {
        return [
            Mission::STEP_ATTENTE_N1,
            Mission::STEP_ATTENTE_DGA,
            Mission::STEP_ATTENTE_MD,
            Mission::STEP_ATTENTE_FACILITIES,
        ];
    }

    private function renvoyerAuDemandeur(Mission $mission): array
    {
        return [
            'current_step' => Mission::STEP_BROUILLON,
            'status' => 'renvoye',
            'md_signe_at' => null,
            'dga_contournee' => false,
        ];
    }

    private function notifierParticipants(Mission $mission, ?string $message = null): void
    {
        MissionNotificationService::notifyParticipantsConcernes(
            $mission,
            $this->libelleEtapeMission($mission),
            $message,
        );
    }

    private function listeCollaborateursMission(User $demandeur): \Illuminate\Support\Collection
    {
        $query = Profil::query()
            ->where('statut', 'actif')
            ->with('roles')
            ->orderBy('nom')
            ->orderBy('prenom');

        if (! $this->demandeurPeutSelectionnerTousMissionnaires($demandeur)) {
            $demandeur->profilCollaborateurAssocie();
            $departementDemandeur = $this->normaliserDepartement(
                $demandeur->profil?->getRawOriginal('departement'),
            );

            if ($departementDemandeur === null) {
                return collect();
            }

            $query->whereNotNull('departement')
                ->where('departement', '!=', '');
        }

        return $query
            ->get()
            ->filter(fn (Profil $profil) => $this->demandeurPeutSelectionnerMissionnaire($demandeur, $profil))
            ->map(fn (Profil $profil) => [
                'id' => $profil->id,
                'prenom' => $profil->prenom,
                'nom' => $profil->nom,
            ])
            ->values();
    }

    private function listeCollaborateursMissionPourEdition(User $demandeur, Mission $mission): \Illuminate\Support\Collection
    {
        $liste = $this->listeCollaborateursMission($demandeur);
        $idsPresents = $liste->pluck('id')->all();
        $idsMission = $this->profilIdsMissionnaires($mission);
        $idsManquants = array_values(array_diff($idsMission, $idsPresents));

        if ($idsManquants === []) {
            return $liste;
        }

        $supplementaires = Profil::query()
            ->whereIn('id', $idsManquants)
            ->where('statut', 'actif')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get()
            ->map(fn (Profil $profil) => [
                'id' => $profil->id,
                'prenom' => $profil->prenom,
                'nom' => $profil->nom,
            ]);

        return $liste->concat($supplementaires)->unique('id')->values();
    }

    private function demandeurPeutSelectionnerTousMissionnaires(User $demandeur): bool
    {
        return $demandeur->isMd() || $demandeur->isDga();
    }

    private function normaliserDepartement(?string $departement): ?string
    {
        if ($departement === null || trim($departement) === '') {
            return null;
        }

        $normalise = preg_replace('/informatique/i', 'IT', trim($departement));

        return mb_strtolower($normalise);
    }

    private function estDepartementRh(?string $departementNormalise): bool
    {
        return $departementNormalise !== null && in_array($departementNormalise, ['rh', 'ressources humaines'], true);
    }

    /**
     * @return array<int, string>
     */
    private function roleSlugsPourUser(User $user): array
    {
        $user->profilCollaborateurAssocie();
        $user->loadMissing('roles');

        $slugs = $user->roles->pluck('slug')->all();

        if ($user->profil) {
            $user->profil->loadMissing('roles');
            $slugs = array_merge($slugs, $user->profil->roles->pluck('slug')->all());
        }

        return array_values(array_unique($slugs));
    }

    /**
     * @return array<int, string>
     */
    private function roleSlugsPourProfil(Profil $profil): array
    {
        $profil->loadMissing('roles');

        $slugs = $profil->roles->pluck('slug')->all();

        $user = $this->trouverUserPourProfil($profil);
        if ($user !== null) {
            $user->loadMissing('roles');
            $slugs = array_merge($slugs, $user->roles->pluck('slug')->all());
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Rôles d'accès applicatif exclus de la règle « même rôle » (ne définissent pas le métier).
     *
     * @return array<int, string>
     */
    private function rolesExclusSelectionMissionnaires(): array
    {
        return ['admin', 'super_admin'];
    }

    /**
     * Rôles métier pris en compte pour la sélection des missionnaires.
     *
     * @param  array<int, string>  $slugs
     * @return array<int, string>
     */
    private function rolesMetierPourSelectionMissionnaires(array $slugs): array
    {
        return array_values(array_diff($slugs, $this->rolesExclusSelectionMissionnaires()));
    }

    /**
     * @param  array<int, string>  $slugsA
     * @param  array<int, string>  $slugsB
     */
    private function partagentAuMoinsUnRole(array $slugsA, array $slugsB): bool
    {
        return array_intersect($slugsA, $slugsB) !== [];
    }

    private function demandeurPeutSelectionnerMissionnaire(User $demandeur, Profil $candidat): bool
    {
        if ($this->demandeurPeutSelectionnerTousMissionnaires($demandeur)) {
            return true;
        }

        $demandeur->profilCollaborateurAssocie();

        if ($demandeur->profil === null) {
            return false;
        }

        $departementDemandeur = $this->normaliserDepartement($demandeur->profil->getRawOriginal('departement'));
        $departementCandidat = $this->normaliserDepartement($candidat->getRawOriginal('departement'));

        if ($departementDemandeur === null || $departementCandidat === null || $departementDemandeur !== $departementCandidat) {
            return false;
        }

        $rolesDemandeur = $this->rolesMetierPourSelectionMissionnaires($this->roleSlugsPourUser($demandeur));
        $rolesCandidat = $this->rolesMetierPourSelectionMissionnaires($this->roleSlugsPourProfil($candidat));

        // Département RH : rh et responsable_rh (RRH) sont éligibles entre eux et avec le reste du département sans rôle métier
        if ($this->estDepartementRh($departementDemandeur)) {
            $rolesRhEligibles = ['rh', 'responsable_rh'];

            if ($rolesDemandeur === [] && $rolesCandidat === []) {
                return true;
            }

            if ($rolesDemandeur === [] || $this->partagentAuMoinsUnRole($rolesDemandeur, $rolesRhEligibles)) {
                return $rolesCandidat === [] || $this->partagentAuMoinsUnRole($rolesCandidat, $rolesRhEligibles);
            }

            return $this->partagentAuMoinsUnRole($rolesDemandeur, $rolesCandidat);
        }

        // Collaborateurs sans rôle métier : le département suffit
        if ($rolesDemandeur === [] && $rolesCandidat === []) {
            return true;
        }

        if ($rolesDemandeur === [] || $rolesCandidat === []) {
            return false;
        }

        return $this->partagentAuMoinsUnRole($rolesDemandeur, $rolesCandidat);
    }

    /**
     * @param  array<int>  $profilIds
     */
    private function asserterMissionnairesAutorises(User $demandeur, array $profilIds): void
    {
        $profils = Profil::query()->whereIn('id', $profilIds)->with('roles')->get();

        foreach ($profilIds as $profilId) {
            $profil = $profils->firstWhere('id', (int) $profilId);

            if ($profil === null || ! $this->demandeurPeutSelectionnerMissionnaire($demandeur, $profil)) {
                throw ValidationException::withMessages([
                    'participant_profil_ids' => 'Un ou plusieurs missionnaires ne sont pas autorisés : même département et même rôle requis (sauf MD/DGA).',
                ]);
            }
        }
    }

    private function listeChauffeurs(): \Illuminate\Support\Collection
    {
        $entrees = collect();

        $profilsChauffeur = Profil::query()
            ->where('statut', 'actif')
            ->whereHas('roles', fn ($q) => $q->where('slug', 'chauffeur'))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        foreach ($profilsChauffeur as $profil) {
            $user = $this->trouverUserPourProfil($profil);
            $name = trim(($profil->prenom ?? '') . ' ' . ($profil->nom ?? ''));
            if ($name === '') {
                $name = $user?->name ?? $profil->email ?? 'Chauffeur';
            }

            $entrees->push([
                'selection_value' => $user !== null ? 'user-' . $user->id : 'profil-' . $profil->id,
                'name' => $name,
                'profil_id' => $profil->id,
                'user_id' => $user?->id,
            ]);
        }

        foreach ($this->utilisateursParRole('chauffeur') as $user) {
            if ($entrees->contains(fn (array $e) => $e['user_id'] === $user->id)) {
                continue;
            }

            $entrees->push([
                'selection_value' => 'user-' . $user->id,
                'name' => $user->name,
                'profil_id' => $this->trouverProfilPourUser($user)?->id,
                'user_id' => $user->id,
            ]);
        }

        return $entrees->sortBy('name')->values();
    }

    private function selectionChauffeurDepuisParticipant(MissionParticipant $participant): string
    {
        if ($participant->chauffeur_id) {
            return 'user-' . $participant->chauffeur_id;
        }

        if ($participant->chauffeur_profil_id) {
            return 'profil-' . $participant->chauffeur_profil_id;
        }

        return '';
    }

    /**
     * @return array{chauffeur_id: ?int, chauffeur_profil_id: ?int}
     */
    private function resoudreSelectionChauffeur(string $selection): array
    {
        if (preg_match('/^user-(\d+)$/', $selection, $matches)) {
            return [
                'chauffeur_id' => (int) $matches[1],
                'chauffeur_profil_id' => null,
            ];
        }

        if (preg_match('/^profil-(\d+)$/', $selection, $matches)) {
            $profilId = (int) $matches[1];
            $profil = Profil::query()->find($profilId);
            $user = $profil ? $this->trouverUserPourProfil($profil) : null;

            return [
                'chauffeur_id' => $user?->id,
                'chauffeur_profil_id' => $profilId,
            ];
        }

        return [
            'chauffeur_id' => null,
            'chauffeur_profil_id' => null,
        ];
    }

    private function nomChauffeurParticipant(MissionParticipant $participant): ?string
    {
        $participant->loadMissing(['chauffeur', 'chauffeurProfil']);

        if ($participant->chauffeur) {
            $profil = $this->trouverProfilPourUser($participant->chauffeur);
            $nom = trim(($profil?->prenom ?? '') . ' ' . ($profil?->nom ?? ''));

            return $nom !== '' ? $nom : $participant->chauffeur->name;
        }

        if ($participant->chauffeurProfil) {
            $nom = trim(($participant->chauffeurProfil->prenom ?? '') . ' ' . ($participant->chauffeurProfil->nom ?? ''));

            return $nom !== '' ? $nom : $participant->chauffeurProfil->email;
        }

        return null;
    }

    private function trouverProfilPourUser(User $user): ?Profil
    {
        $user->profilCollaborateurAssocie();

        return $user->profil;
    }

    private function trouverUserPourProfil(Profil $profil): ?User
    {
        $email = strtolower(trim((string) $profil->email));
        if ($email === '') {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();
    }

    /**
     * @param  array<int>  $profilIds
     */
    private function syncMissionnairesFromProfilIds(Mission $mission, array $profilIds): void
    {
        MissionParticipant::query()
            ->where('mission_id', $mission->id)
            ->where('role_dans_mission', 'missionnaire')
            ->delete();

        $mission->participants()->wherePivot('role_dans_mission', 'missionnaire')->detach();

        $profils = Profil::query()->whereIn('id', $profilIds)->get()->keyBy('id');

        foreach ($profilIds as $profilId) {
            $profil = $profils->get($profilId);
            if ($profil === null) {
                continue;
            }

            $user = $this->trouverUserPourProfil($profil);

            MissionParticipant::create([
                'mission_id' => $mission->id,
                'profil_id' => $profilId,
                'user_id' => $user?->id,
                'role_dans_mission' => 'missionnaire',
            ]);
        }
    }

    /**
     * @return array<int>
     */
    private function profilIdsMissionnaires(Mission $mission): array
    {
        return MissionParticipant::query()
            ->where('mission_id', $mission->id)
            ->where('role_dans_mission', 'missionnaire')
            ->whereNotNull('profil_id')
            ->pluck('profil_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function premierUserIdDepuisProfils(array $profilIds): ?int
    {
        $profils = Profil::query()->whereIn('id', $profilIds)->get()->keyBy('id');

        foreach ($profilIds as $profilId) {
            $profil = $profils->get($profilId);
            if ($profil === null) {
                continue;
            }

            $user = $this->trouverUserPourProfil($profil);
            if ($user !== null) {
                return $user->id;
            }
        }

        return null;
    }

    private function peutVoirTousDetailsLogistique(User $user): bool
    {
        return $user->isLogistique() || $user->isFinance();
    }

    private function formaterLogistiqueMissionParticipant(
        MissionParticipant $participant,
        User $viewer,
        bool $estLigneFraisPrincipale = true,
    ): array {
        $participant->loadMissing(['chauffeur', 'chauffeurProfil']);
        $chauffeurId = $participant->chauffeur_id;
        $afficheFrais = $estLigneFraisPrincipale || ! $participant->besoin_chauffeur;

        $logistique = [
            'besoin_chauffeur' => (bool) $participant->besoin_chauffeur,
            'chauffeur_id' => $chauffeurId,
            'chauffeur_profil_id' => $participant->chauffeur_profil_id,
            'chauffeur_selection' => $this->selectionChauffeurDepuisParticipant($participant),
            'chauffeur_nom' => $this->nomChauffeurParticipant($participant),
            'affiche_frais_detail' => $afficheFrais,
        ];

        if ($this->peutVoirTousDetailsLogistique($viewer)) {
            $totalLigne = $afficheFrais
                ? (float) ($participant->per_diem ?? 0)
                    + (float) ($participant->prix_carburant ?? 0)
                    + (float) ($participant->prix_transport ?? 0)
                    + (float) ($participant->prix_logement ?? 0)
                    + (float) ($participant->autres_frais ?? 0)
                : 0;

            return array_merge($logistique, [
                'vehicule' => $afficheFrais ? $participant->vehicule : null,
                'logement' => $afficheFrais ? $participant->logement : null,
                'per_diem' => $afficheFrais ? (float) ($participant->per_diem ?? 0) : 0,
                'prix_carburant' => $afficheFrais ? (float) ($participant->prix_carburant ?? 0) : 0,
                'prix_transport' => $afficheFrais ? (float) ($participant->prix_transport ?? 0) : 0,
                'prix_logement' => $afficheFrais ? (float) ($participant->prix_logement ?? 0) : 0,
                'autres_frais' => $afficheFrais ? (float) ($participant->autres_frais ?? 0) : 0,
                'total_ligne' => $totalLigne,
            ]);
        }

        if ($participant->user_id === $viewer->id) {
            $logistique['per_diem'] = (float) ($participant->per_diem ?? 0);
        }

        if ((int) $chauffeurId === $viewer->id) {
            $logistique['prix_carburant'] = (float) ($participant->prix_carburant ?? 0);
        }

        return $logistique;
    }

    private function formaterMissionnairesPourAffichage(
        \Illuminate\Support\Collection $missionnaires,
        User $viewer,
    ): array {
        $lignesPrincipalesChauffeur = $this->idsMissionnairesPrincipauxChauffeur($missionnaires);

        return $missionnaires
            ->map(function (MissionParticipant $participant) use ($viewer, $lignesPrincipalesChauffeur) {
                $participant->loadMissing(['profil', 'user']);
                $estLignePrincipale = $lignesPrincipalesChauffeur[$participant->id] ?? true;

                return [
                    'id' => $participant->id,
                    'user_id' => $participant->user_id,
                    'profil_id' => $participant->profil_id,
                    'prenom' => $participant->profil?->prenom,
                    'nom' => $participant->profil?->nom,
                    'name' => $participant->nomAffichage(),
                    'pivot' => [
                        'role_dans_mission' => 'missionnaire',
                    ],
                    'logistique' => $this->formaterLogistiqueMissionParticipant(
                        $participant,
                        $viewer,
                        $estLignePrincipale,
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, bool> participant_id => est ligne principale des frais chauffeur
     */
    private function idsMissionnairesPrincipauxChauffeur(\Illuminate\Support\Collection $missionnaires): array
    {
        $principaux = [];

        foreach ($missionnaires->groupBy(fn (MissionParticipant $p) => $this->selectionChauffeurDepuisParticipant($p)) as $cle => $groupe) {
            if ($cle === '') {
                foreach ($groupe as $participant) {
                    $principaux[$participant->id] = true;
                }

                continue;
            }

            $principalId = $groupe->min('id');
            foreach ($groupe as $participant) {
                $principaux[$participant->id] = $participant->id === $principalId;
            }
        }

        return $principaux;
    }

    private function estSaisieFacilitiesProlongation(Mission $mission): bool
    {
        return $mission->current_step === Mission::STEP_ATTENTE_FACILITIES
            && is_array($mission->prolongation_donnees)
            && ($mission->prolongation_donnees['type'] ?? '') === 'prolongation';
    }

    /**
     * @param  array<string, mixed>  $ligne
     * @return array<string, mixed>
     */
    private function normaliserLigneLogistiqueSite(array $ligne): array
    {
        $nuits = max(0, (int) ($ligne['nuits'] ?? 0));
        $prixNuit = max(0, (float) ($ligne['prix_nuit'] ?? 0));
        $jours = max(0, (int) ($ligne['jours'] ?? 0));
        $prixJournalier = max(0, (float) ($ligne['prix_journalier'] ?? 0));

        return [
            'site' => (string) ($ligne['site'] ?? ''),
            'phase' => in_array($ligne['phase'] ?? '', ['initiale', 'prolongation'], true) ? $ligne['phase'] : 'initiale',
            'verrouille' => (bool) ($ligne['verrouille'] ?? false),
            'nuits' => $nuits,
            'prix_nuit' => $prixNuit,
            'total_logement' => round($nuits * $prixNuit, 2),
            'jours' => $jours,
            'prix_journalier' => $prixJournalier,
            'total_per_diem' => round($jours * $prixJournalier, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $soumis
     * @return array{lignes: array<int, array<string, mixed>>, per_diem: float, prix_logement: float}
     */
    private function fusionnerLogistiqueSitesParticipant(MissionParticipant $participant, array $soumis): array
    {
        $verrouillees = collect($participant->logistique_sites ?? [])
            ->filter(fn (array $l) => (bool) ($l['verrouille'] ?? false))
            ->map(fn (array $l) => $this->normaliserLigneLogistiqueSite(array_merge($l, ['verrouille' => true])));

        $editables = collect($soumis)
            ->reject(fn (array $l) => (bool) ($l['verrouille'] ?? false))
            ->map(fn (array $l) => $this->normaliserLigneLogistiqueSite($l));

        return $this->totauxDepuisLogistiqueSites($verrouillees->merge($editables)->values()->all());
    }

    /**
     * @param  array<int, array<string, mixed>>  $lignes
     * @return array{lignes: array<int, array<string, mixed>>, per_diem: float, prix_logement: float}
     */
    private function totauxDepuisLogistiqueSites(array $lignes): array
    {
        $lignes = array_map(fn (array $l) => $this->normaliserLigneLogistiqueSite($l), $lignes);

        return [
            'lignes' => $lignes,
            'per_diem' => round((float) collect($lignes)->sum('total_per_diem'), 2),
            'prix_logement' => round((float) collect($lignes)->sum('total_logement'), 2),
        ];
    }

    private function archiverLogistiqueSitesParticipant(MissionParticipant $participant): void
    {
        $lignes = collect($participant->logistique_sites ?? [])
            ->map(function (array $ligne) {
                $ligne = $this->normaliserLigneLogistiqueSite($ligne);
                $ligne['verrouille'] = true;

                return $ligne;
            })
            ->values()
            ->all();

        if ($lignes !== []) {
            $participant->update(['logistique_sites' => $lignes]);
        }
    }

    /**
     * @return array{jours: int, nuits: int}
     */
    private function calculerJoursNuitsPeriode(Carbon $debut, Carbon $fin): array
    {
        if ($fin->lt($debut)) {
            return ['jours' => 1, 'nuits' => 0];
        }

        $jours = max(1, $debut->diffInDays($fin) + 1);

        return [
            'jours' => $jours,
            'nuits' => max(0, $jours - 1),
        ];
    }

    /**
     * @return array{jours: int, nuits: int}
     */
    private function calculerJoursNuitsMission(Mission $mission, string $phaseCible): array
    {
        if ($phaseCible === 'prolongation' && $this->estSaisieFacilitiesProlongation($mission)) {
            $donnees = $mission->prolongation_donnees ?? [];
            $ancienFin = isset($donnees['ancien_fin'])
                ? Carbon::createFromFormat('d/m/Y', (string) $donnees['ancien_fin'])
                : null;
            $nouveauFin = isset($donnees['nouveau_fin'])
                ? Carbon::createFromFormat('d/m/Y', (string) $donnees['nouveau_fin'])
                : null;

            if ($ancienFin !== null && $nouveauFin !== null && $nouveauFin->gt($ancienFin)) {
                return $this->calculerJoursNuitsPeriode($ancienFin->copy()->addDay(), $nouveauFin);
            }
        }

        if ($mission->date_debut === null || $mission->date_fin === null) {
            return ['jours' => 1, 'nuits' => 0];
        }

        return $this->calculerJoursNuitsPeriode(
            Carbon::parse($mission->date_debut)->startOfDay(),
            Carbon::parse($mission->date_fin)->startOfDay(),
        );
    }

    /**
     * @param  array<int, string>  $sites
     * @param  array<int, array<string, mixed>>|null  $existantes
     * @return array<int, array<string, mixed>>
     */
    private function construireLignesLogistiqueSitesMission(Mission $mission, ?array $existantes, string $phaseCible, array $sites): array
    {
        $existantes = collect($existantes ?? []);
        $lignes = [];
        $duree = $this->calculerJoursNuitsMission($mission, $phaseCible);

        foreach ($sites as $site) {
            $trouvee = $existantes->first(
                fn (array $l) => ($l['site'] ?? '') === $site && ($l['phase'] ?? 'initiale') === $phaseCible,
            );

            if ($trouvee) {
                $lignes[] = $this->normaliserLigneLogistiqueSite($trouvee);

                continue;
            }

            $lignes[] = $this->normaliserLigneLogistiqueSite([
                'site' => $site,
                'phase' => $phaseCible,
                'verrouille' => $phaseCible === 'initiale' && $this->estSaisieFacilitiesProlongation($mission),
                'nuits' => $duree['nuits'],
                'prix_nuit' => 0,
                'jours' => $duree['jours'],
                'prix_journalier' => 0,
            ]);
        }

        return $lignes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reconstituerLogistiqueSitesParticipant(Mission $mission, MissionParticipant $participant): array
    {
        $existantes = $participant->logistique_sites ?? [];

        if ($this->estSaisieFacilitiesProlongation($mission)) {
            $archivees = collect($existantes)
                ->map(fn (array $l) => $this->normaliserLigneLogistiqueSite(array_merge($l, ['verrouille' => true])))
                ->values()
                ->all();

            $sitesProlongation = $mission->sites_prolongation ?? [];

            return array_merge(
                $archivees,
                $this->construireLignesLogistiqueSitesMission($mission, $existantes, 'prolongation', $sitesProlongation),
            );
        }

        $sites = $mission->sites_mission ?? MissionSites::extraireDepuisPerimetre($mission->perimetre);

        return $this->construireLignesLogistiqueSitesMission($mission, $existantes, 'initiale', $sites);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MissionParticipant>  $missionnaires
     * @return array{chauffeurs_logistique: array<int, array<string, mixed>>, missionnaires_autonomes: array<int, array<string, mixed>>, est_prolongation: bool}
     */
    private function reconstituerFormulaireFacilities(\Illuminate\Support\Collection $missionnaires, Mission $mission): array
    {
        $groupes = [];
        $autonomes = [];

        foreach ($missionnaires as $participant) {
            $autonomes[] = [
                'participant_id' => $participant->id,
                'logement' => $participant->logement ?? '',
                'per_diem' => (float) ($participant->per_diem ?? 0),
                'prix_transport' => (float) ($participant->prix_transport ?? 0),
                'prix_logement' => (float) ($participant->prix_logement ?? 0),
                'autres_frais' => (float) ($participant->autres_frais ?? 0),
                'logistique_sites' => $this->reconstituerLogistiqueSitesParticipant($mission, $participant),
            ];

            if ($participant->besoin_chauffeur && $this->selectionChauffeurDepuisParticipant($participant) !== '') {
                $cle = $this->selectionChauffeurDepuisParticipant($participant);

                if (! isset($groupes[$cle])) {
                    $groupes[$cle] = [
                        'chauffeur_selection' => $cle,
                        'participant_ids' => [],
                        'vehicule' => $participant->vehicule ?? '',
                        'logement' => $participant->logement ?? '',
                        'per_diem' => (float) ($participant->per_diem ?? 0),
                        'prix_carburant' => (float) ($participant->prix_carburant ?? 0),
                        'prix_logement' => (float) ($participant->prix_logement ?? 0),
                        'autres_frais' => (float) ($participant->autres_frais ?? 0),
                    ];
                }

                $groupes[$cle]['participant_ids'][] = $participant->id;

                if ((float) ($participant->prix_carburant ?? 0) > 0) {
                    $groupes[$cle]['vehicule'] = $participant->vehicule ?? $groupes[$cle]['vehicule'];
                    $groupes[$cle]['prix_carburant'] = (float) ($participant->prix_carburant ?? 0);
                    $groupes[$cle]['per_diem'] = (float) ($participant->per_diem ?? 0);
                    $groupes[$cle]['prix_logement'] = (float) ($participant->prix_logement ?? 0);
                    $groupes[$cle]['autres_frais'] = (float) ($participant->autres_frais ?? 0);
                    $groupes[$cle]['logement'] = $participant->logement ?? $groupes[$cle]['logement'];
                }
            }
        }

        return [
            'chauffeurs_logistique' => array_values($groupes),
            'missionnaires_autonomes' => $autonomes,
            'est_prolongation' => $this->estSaisieFacilitiesProlongation($mission),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $chauffeursLogistique
     * @param  array<int, array<string, mixed>>  $missionnairesAutonomes
     */
    private function calculerTotalLogistiqueFacilities(array $chauffeursLogistique, array $missionnairesAutonomes): float
    {
        $totalChauffeurs = (float) collect($chauffeursLogistique)->sum(function (array $bloc) {
            return (float) ($bloc['per_diem'] ?? 0)
                + (float) ($bloc['prix_carburant'] ?? 0)
                + (float) ($bloc['prix_logement'] ?? 0)
                + (float) ($bloc['autres_frais'] ?? 0);
        });

        $totalAutonomes = (float) collect($missionnairesAutonomes)->sum(function (array $ligne) {
            $sites = $this->totauxDepuisLogistiqueSites($ligne['logistique_sites'] ?? []);

            return $sites['per_diem'] + $sites['prix_logement'] + (float) ($ligne['autres_frais'] ?? 0);
        });

        return $totalChauffeurs + $totalAutonomes;
    }

    /**
     * @param  array<int, string>  $sites
     * @return array{sites_mission: array<int, string>, perimetre: string}
     */
    private function preparerSitesMissionPayload(array $sites): array
    {
        $sitesValides = MissionSites::validerSelection($sites);

        return [
            'sites_mission' => $sitesValides,
            'perimetre' => MissionSites::perimetreDepuisSites($sitesValides),
        ];
    }

    /**
     * @param  array<int, string>  $sites
     * @param  array<string, mixed>  $descriptions
     * @return array<string, string>
     */
    private function validerDescriptionsSites(array $sites, array $descriptions): array
    {
        $sitesValides = MissionSites::validerSelection($sites);
        $resultat = [];

        foreach ($sitesValides as $site) {
            $texte = trim((string) ($descriptions[$site] ?? ''));
            if ($texte === '' || mb_strlen($texte) < 5) {
                throw ValidationException::withMessages([
                    "descriptions_sites.{$site}" => "La raison de la mission pour « {$site} » est obligatoire (5 caractères minimum).",
                ]);
            }
            $resultat[$site] = $texte;
        }

        return $resultat;
    }

    private function estProlongationEnAttenteSignature(Mission $mission): bool
    {
        $donnees = $mission->prolongation_donnees;

        return is_array($donnees) && ($donnees['type'] ?? '') === 'prolongation';
    }

    /**
     * @return array<int, string>
     */
    private function sitesMissionPourFormulaire(Mission $mission): array
    {
        $sites = $mission->sites_mission ?? [];
        if ($sites !== []) {
            return MissionSites::validerSelection($sites);
        }

        return MissionSites::extraireDepuisPerimetre($mission->perimetre);
    }

    private function destinationsAffichageMission(Mission $mission): string
    {
        $sites = $this->sitesMissionPourFormulaire($mission);
        if ($sites !== []) {
            return implode(' / ', $sites);
        }

        $perimetre = trim((string) ($mission->perimetre ?? ''));
        if ($perimetre === '') {
            return '—';
        }

        return preg_replace('/\s*,\s*/', ' / ', $perimetre) ?? $perimetre;
    }

    private function reglesMission(bool $soumission = true): array
    {
        $regles = [
            'participant_profil_ids' => ['required', 'array', 'min:1'],
            'participant_profil_ids.*' => ['exists:profiles,id'],
            'objet' => ['required', 'string', 'max:255'],
            'sites_mission' => ['required', 'array', 'min:1'],
            'sites_mission.*' => ['string', Rule::in(MissionSites::allLabels())],
            'description' => ['required', 'string'],
            'descriptions_sites' => ['required', 'array'],
            'priorite' => ['required', 'in:normale,urgente,critique'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
        ];

        if ($soumission) {
            $regles['date_debut'][] = 'after_or_equal:today';
        }

        return $regles;
    }

    private function utilisateursParRole(string $slug): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', $slug))
            ->orWhereHas('profil.roles', fn ($q) => $q->where('slug', $slug))
            ->get();
    }

    private function notifierRole(string $roleSlug, Mission $mission, string $etapeLabel): void
    {
        $url = match ($mission->current_step) {
            Mission::STEP_ATTENTE_N1 => route('missions.show', $mission),
            Mission::STEP_ATTENTE_DGA => route('missions.validation-dga'),
            Mission::STEP_ATTENTE_MD => route('missions.validation-md'),
            Mission::STEP_ATTENTE_FACILITIES => route('missions.validation-facilities'),
            Mission::STEP_ATTENTE_RH => route('missions.validation-rh-logistique'),
            default => route('missions.show', $mission),
        };

        foreach ($this->utilisateursParRole($roleSlug) as $user) {
            MissionNotificationService::notifyEtape($mission, $user, $etapeLabel, $url);
        }
    }

    private function logoDataUriOrdreMission(): ?string
    {
        $logoPath = public_path('logo_Cofina.png');
        if (! is_readable($logoPath)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath));
    }

    private function reglesSignatureMission(): array
    {
        return ['nullable', 'string', 'max:500000', 'regex:/^data:image\/(png|jpe?g|webp);base64,/'];
    }

    private function optimiserSignatureImage(?string $signature): ?string
    {
        if ($signature === null || $signature === '' || ! extension_loaded('gd')) {
            return $signature;
        }

        if (! preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/i', $signature, $matches)) {
            return $signature;
        }

        $data = base64_decode($matches[2], true);
        if ($data === false) {
            return $signature;
        }

        $image = @imagecreatefromstring($data);
        if ($image === false) {
            return $signature;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxWidth = 400;
        $maxHeight = 150;

        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = max(1, (int) round($width * $ratio));
            $newHeight = max(1, (int) round($height * $ratio));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imagepng($image, null, 6);
        $png = ob_get_clean();
        imagedestroy($image);

        if ($png === false || $png === '') {
            return $signature;
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }

    private function mdASigne(Mission $mission): bool
    {
        return $mission->md_signe_at !== null;
    }

    private function redirectApresActionEtape(string $etapeActuelle, string $flashType, string $message, ?User $user = null): RedirectResponse
    {
        $route = match ($etapeActuelle) {
            Mission::STEP_ATTENTE_N1, Mission::STEP_ATTENTE_DGA => ($user && ($user->isDga() || $user->estDesigneN1DunProfil()))
                ? 'missions.validation-dga'
                : 'missions.index',
            Mission::STEP_ATTENTE_MD => ($user && $user->isMd()) ? 'missions.validation-md' : 'missions.index',
            Mission::STEP_ATTENTE_FACILITIES => ($user && $user->isLogistique()) ? 'missions.validation-facilities' : 'missions.index',
            Mission::STEP_ATTENTE_RH, 'ATTENTE_RH_LOGISTIQUE' => ($user && $this->peutValiderRh($user))
                ? 'missions.validation-rh-logistique'
                : 'missions.index',
            Mission::STEP_ATTENTE_SIGNATURE_RRH => ($user && $user->isResponsableRh())
                ? 'missions.validation-signature-rrh'
                : 'missions.index',
            Mission::STEP_ATTENTE_RAPPORT, Mission::STEP_ATTENTE_VALIDATION_RAPPORT => 'missions.rapports',
            default => 'missions.index',
        };

        return redirect()->route($route)->with($flashType, $message);
    }

    private function peutImprimerFicheValidation(User $user, Mission $mission): bool
    {
        if (! $this->mdASigne($mission)) {
            return false;
        }

        return $user->isAdmin()
            || $user->isAudit()
            || $this->estDemandeur($user, $mission)
            || $this->estParticipant($user, $mission)
            || $this->peutTraiterEtapeCourante($user, $mission)
            || $user->isLogistique()
            || $user->isFinance()
            || $user->isRh()
            || $user->isDga()
            || $user->isMd();
    }

    private function nomSignatairePourUser(?User $user): string
    {
        if ($user === null) {
            return '—';
        }

        $profil = $this->trouverProfilPourUser($user);
        if ($profil !== null) {
            $nomComplet = trim(($profil->prenom ?? '') . ' ' . ($profil->nom ?? ''));
            if ($nomComplet !== '') {
                return $nomComplet;
            }
        }

        return $user->name ?? '—';
    }

    private function peutVoirHistoriqueMission(User $user): bool
    {
        return $user->peutVoirHistoriqueMissions();
    }

    private function signatureDepuisLogs(Mission $mission, string $motifEtape): ?array
    {
        $log = $mission->logs
            ->filter(fn (MissionLog $l) => str_contains($l->etape_concernee, $motifEtape) && $l->signature_image)
            ->sortByDesc('created_at')
            ->first();

        if ($log === null) {
            return null;
        }

        $log->loadMissing('auteur');

        return [
            'nom' => $this->nomSignatairePourUser($log->auteur),
            'date' => $log->created_at?->format('d/m/Y') ?? '—',
            'image' => $log->signature_image,
        ];
    }

    private function preparerDonneesFicheValidation(Mission $mission): array
    {
        $mission->loadMissing(['demandeur', 'participants', 'logs.auteur']);

        $profilDemandeur = $this->trouverProfilPourUser($mission->demandeur);
        $profilDemandeur?->loadMissing('filiale');

        $beneficiaires = $this->missionnairesMission($mission)
            ->map(function (MissionParticipant $participant) {
                $participant->loadMissing('profil');

                return [
                    'nom' => $participant->nomAffichage(),
                    'fonction' => $participant->profil?->fonction ?? '—',
                ];
            })
            ->values()
            ->all();

        $dureeJours = 1;
        if ($mission->date_debut && $mission->date_fin) {
            $dureeJours = max(1, $mission->date_debut->diffInDays($mission->date_fin) + 1);
        }

        return [
            'logoDataUri' => $this->logoDataUriOrdreMission(),
            'filiale' => $profilDemandeur?->filiale?->nom ?? $profilDemandeur?->site ?? '—',
            'departement' => $profilDemandeur?->departement ?? '—',
            'demandeur' => [
                'nom' => $profilDemandeur
                    ? trim($profilDemandeur->prenom . ' ' . $profilDemandeur->nom)
                    : ($mission->demandeur?->name ?? '—'),
                'fonction' => $profilDemandeur?->fonction ?? '—',
                'email' => $profilDemandeur?->email ?? $mission->demandeur?->email ?? '—',
                'telephone' => $profilDemandeur?->telephone ?? '—',
            ],
            'beneficiaires' => $beneficiaires,
            'date_demande' => $mission->created_at?->format('d/m/Y') ?? '—',
            'destination' => $this->destinationsAffichageMission($mission),
            'objet' => $mission->objet ?? '—',
            'duree' => $dureeJours . ' jour' . ($dureeJours > 1 ? 's' : ''),
            'date_debut' => $mission->date_debut?->format('d/m/Y') ?? '—',
            'date_fin' => $mission->date_fin?->format('d/m/Y') ?? '—',
            'motif' => trim($mission->description ?? ''),
            'signatures' => $this->signaturesPourFicheValidation($mission),
        ];
    }

    /**
     * @return array{content: string, hash: string}
     */
    private function construirePdfFicheValidation(Mission $mission): array
    {
        $html = view('missions.demande-validation', $this->preparerDonneesFicheValidation($mission))->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $content = $dompdf->output();

        return [
            'content' => $content,
            'hash' => hash('sha256', $content),
        ];
    }

    private function enregistrerLogValidation(
        Mission $mission,
        User $auteur,
        string $etapeConcernee,
        ?string $signature,
        ?string $commentaire = null,
    ): MissionLog {
        $signatureOptimisee = $signature ? $this->optimiserSignatureImage($signature) : null;

        return MissionLog::create([
            'mission_id' => $mission->id,
            'user_id' => $auteur->id,
            'action' => 'approbation',
            'etape_concernee' => $etapeConcernee,
            'commentaire' => $commentaire,
            'signature_image' => $signatureOptimisee,
            'signature_hash' => $signature ? hash('sha256', $signature) : null,
        ]);
    }

    private function formaterDateMissionFr(?\DateTimeInterface $date): string
    {
        if ($date === null) {
            return '—';
        }

        Carbon::setLocale('fr');

        return mb_strtolower(Carbon::instance($date)->translatedFormat('l j F Y'));
    }

    private function typeDeplacementMission(Mission $mission): string
    {
        if ($mission->date_debut && $mission->date_fin && $mission->date_debut->equalTo($mission->date_fin)) {
            return 'aller - retour';
        }

        return 'aller - retour';
    }

    /**
     * @return array<int, array{nom_complet: string, fonction: string, destination: string, date_mission: string, type_deplacement: string, objet: string}>
     */
    private function preparerOrdresMission(Mission $mission): array
    {
        $mission->loadMissing(['chauffeur']);

        $missionnaires = $this->missionnairesMission($mission);

        $descriptionGlobale = trim((string) ($mission->description ?? ''));

        $ordres = [];
        foreach ($missionnaires as $missionnaire) {
            $missionnaire->loadMissing('profil');
            $profil = $missionnaire->profil;
            $prenom = trim((string) ($profil?->prenom ?? ''));
            $nom = trim((string) ($profil?->nom ?? ''));
            $nomComplet = $prenom !== '' && $nom !== ''
                ? $prenom . ' ' . mb_strtoupper($nom)
                : $missionnaire->nomAffichage();

            $ordres[] = [
                'nom_complet' => $nomComplet,
                'fonction' => mb_strtoupper(trim((string) ($profil?->fonction ?? 'COLLABORATEUR'))),
                'destination' => mb_strtoupper($this->destinationsAffichageMission($mission)),
                'date_mission' => $this->formaterDateMissionFr($mission->date_debut),
                'date_debut' => $mission->date_debut?->format('d/m/Y') ?? '—',
                'date_fin' => $mission->date_fin?->format('d/m/Y') ?? '—',
                'type_deplacement' => $this->typeDeplacementMission($mission),
                'objet' => mb_strtoupper(trim((string) $mission->objet)),
                'description_globale' => $descriptionGlobale,
            ];
        }

        $chauffeursDejaAjoutes = collect();

        foreach ($missionnaires as $missionnaire) {
            $missionnaire->loadMissing(['chauffeur', 'chauffeurProfil']);

            if ($missionnaire->chauffeur) {
                $cle = 'user-' . $missionnaire->chauffeur_id;
                if ($chauffeursDejaAjoutes->contains($cle)) {
                    continue;
                }
                $chauffeursDejaAjoutes->push($cle);

                $profil = $this->trouverProfilPourUser($missionnaire->chauffeur);
                $prenom = trim((string) ($profil?->prenom ?? ''));
                $nom = trim((string) ($profil?->nom ?? ''));
                $nomComplet = $prenom !== '' && $nom !== ''
                    ? $prenom . ' ' . mb_strtoupper($nom)
                    : ($missionnaire->chauffeur->name ?? '—');
                $fonction = mb_strtoupper(trim((string) ($profil?->fonction ?? 'CHAUFFEUR')));
            } elseif ($missionnaire->chauffeurProfil) {
                $cle = 'profil-' . $missionnaire->chauffeur_profil_id;
                if ($chauffeursDejaAjoutes->contains($cle)) {
                    continue;
                }
                $chauffeursDejaAjoutes->push($cle);

                $profil = $missionnaire->chauffeurProfil;
                $prenom = trim((string) ($profil->prenom ?? ''));
                $nom = trim((string) ($profil->nom ?? ''));
                $nomComplet = $prenom !== '' && $nom !== ''
                    ? $prenom . ' ' . mb_strtoupper($nom)
                    : ($profil->email ?? '—');
                $fonction = mb_strtoupper(trim((string) ($profil->fonction ?? 'CHAUFFEUR')));
            } else {
                continue;
            }

            $ordres[] = [
                'nom_complet' => $nomComplet,
                'fonction' => $fonction,
                'destination' => mb_strtoupper($this->destinationsAffichageMission($mission)),
                'date_mission' => $this->formaterDateMissionFr($mission->date_debut),
                'date_debut' => $mission->date_debut?->format('d/m/Y') ?? '—',
                'date_fin' => $mission->date_fin?->format('d/m/Y') ?? '—',
                'type_deplacement' => $this->typeDeplacementMission($mission),
                'objet' => mb_strtoupper(trim((string) $mission->objet)),
                'description_globale' => $descriptionGlobale,
            ];
        }

        if ($chauffeursDejaAjoutes->isEmpty() && $mission->chauffeur_id) {
            $chauffeur = User::query()->find($mission->chauffeur_id);
            if ($chauffeur) {
                $profil = $this->trouverProfilPourUser($chauffeur);
                $prenom = trim((string) ($profil?->prenom ?? ''));
                $nom = trim((string) ($profil?->nom ?? ''));
                $ordres[] = [
                    'nom_complet' => $prenom !== '' && $nom !== ''
                        ? $prenom . ' ' . mb_strtoupper($nom)
                        : ($chauffeur->name ?? '—'),
                    'fonction' => mb_strtoupper(trim((string) ($profil?->fonction ?? 'CHAUFFEUR'))),
                    'destination' => mb_strtoupper($this->destinationsAffichageMission($mission)),
                    'date_mission' => $this->formaterDateMissionFr($mission->date_debut),
                    'date_debut' => $mission->date_debut?->format('d/m/Y') ?? '—',
                    'date_fin' => $mission->date_fin?->format('d/m/Y') ?? '—',
                    'type_deplacement' => $this->typeDeplacementMission($mission),
                    'objet' => mb_strtoupper(trim((string) $mission->objet)),
                    'description_globale' => $descriptionGlobale,
                ];
            }
        }

        return $ordres;
    }

    /**
     * @return array{content: string, hash: string}
     */
    private function construirePdfOrdreMission(Mission $mission, ?string $signatureRrh = null, ?string $signatureRrhNom = null): array
    {
        $ordres = $this->preparerOrdresMission($mission);

        if ($ordres === []) {
            throw new \RuntimeException('Aucun missionnaire trouvé pour générer l\'ordre de mission.');
        }

        $html = view('missions.ordre-mission', [
            'ordres' => $ordres,
            'logoDataUri' => $this->logoDataUriOrdreMission(),
            'signatureRrh' => $signatureRrh,
            'signatureRrhNom' => $signatureRrhNom,
            'missionId' => $this->numeroMissionPourAffichage($mission) ?? '—',
            'dateGeneration' => now()->format('d/m/Y'),
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $content = $dompdf->output();

        return [
            'content' => $content,
            'hash' => hash('sha256', $content),
        ];
    }

    private function genererPdfMission(Mission $mission, ?string $signatureRrh = null, ?User $signataireRrh = null): string
    {
        $nomSignataire = $signatureRrh !== null ? $this->nomSignatairePourUser($signataireRrh) : null;
        $pdf = $this->construirePdfOrdreMission($mission, $signatureRrh, $nomSignataire);
        $filename = "missions/ordre_mission_{$mission->id}_" . now()->format('Y-m-d_His') . '.pdf';
        Storage::disk('local')->put($filename, $pdf['content']);

        return $filename;
    }

    /**
     * @return array{0: ?string, 1: ?string} signature image, nom signataire
     */
    private function signatureRrhOrdreMission(Mission $mission): array
    {
        $log = $mission->logs()
            ->where('etape_concernee', 'like', '%Signature Responsable RH%')
            ->whereNotNull('signature_image')
            ->latest()
            ->first();

        if ($log === null) {
            return [null, null];
        }

        $log->loadMissing('auteur');

        return [
            $log->signature_image,
            $this->nomSignatairePourUser($log->auteur),
        ];
    }

    private function regenererPdfOrdreMissionStocke(Mission $mission): string
    {
        [$signature, $nomSignataire] = $this->signatureRrhOrdreMission($mission);

        if ($mission->pdf_path && Storage::disk('local')->exists($mission->pdf_path)) {
            Storage::disk('local')->delete($mission->pdf_path);
        }

        $pdf = $this->construirePdfOrdreMission($mission, $signature, $nomSignataire);
        $filename = "missions/ordre_mission_{$mission->id}_" . now()->format('Y-m-d_His') . '.pdf';
        Storage::disk('local')->put($filename, $pdf['content']);
        $mission->update(['pdf_path' => $filename]);

        return $pdf['content'];
    }

    /**
     * @return array{content: string, hash: string}
     */
    private function construirePdfOrdreProlongation(Mission $mission, ?string $signatureRrh = null, ?string $signatureRrhNom = null): array
    {
        $donnees = $mission->prolongation_donnees ?? [];
        $mission->loadMissing('demandeur');

        $missionnaires = implode(', ', $donnees['missionnaires'] ?? []);

        $html = view('missions.ordre-prolongation', [
            'mission' => $mission,
            'logoDataUri' => $this->logoDataUriOrdreMission(),
            'signatureRrh' => $signatureRrh,
            'signatureRrhNom' => $signatureRrhNom,
            'dateGeneration' => now()->format('d/m/Y'),
            'demandeur' => $mission->demandeur?->name ?? '—',
            'destination' => $this->destinationsAffichageMission($mission),
            'ancienDebut' => $donnees['ancien_debut'] ?? '—',
            'ancienFin' => $donnees['ancien_fin'] ?? '—',
            'nouveauDebut' => $donnees['nouveau_debut'] ?? ($mission->date_debut?->format('d/m/Y') ?? '—'),
            'nouveauFin' => $donnees['nouveau_fin'] ?? ($mission->date_fin?->format('d/m/Y') ?? '—'),
            'motif' => $donnees['motif'] ?? '—',
            'missionnaires' => $missionnaires !== '' ? $missionnaires : '—',
            'descriptionGlobale' => trim((string) ($mission->description ?? '')),
            'sitesProlongation' => $mission->sites_prolongation ?? ($donnees['sites_prolongation'] ?? []),
            'descriptionsSitesProlongation' => $mission->descriptions_sites_prolongation ?? ($donnees['descriptions_sites_prolongation'] ?? []),
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $content = $dompdf->output();

        return [
            'content' => $content,
            'hash' => hash('sha256', $content),
        ];
    }

    private function genererPdfOrdreProlongation(Mission $mission, ?string $signatureRrh = null, ?User $signataireRrh = null): string
    {
        $nomSignataire = $signatureRrh !== null ? $this->nomSignatairePourUser($signataireRrh) : null;
        $pdf = $this->construirePdfOrdreProlongation($mission, $signatureRrh, $nomSignataire);
        $filename = "missions/ordre_prolongation_{$mission->id}_" . now()->format('Y-m-d_His') . '.pdf';
        Storage::disk('local')->put($filename, $pdf['content']);

        return $filename;
    }

    private function peutTelechargerPdfOrdre(User $user, Mission $mission, bool $estParticipant): bool
    {
        if (! $mission->pdf_path) {
            return false;
        }

        $etapesPdfDisponible = [
            Mission::STEP_VALIDEE,
            Mission::STEP_ATTENTE_RAPPORT,
            Mission::STEP_ATTENTE_VALIDATION_RAPPORT,
            Mission::STEP_CLOTUREE,
        ];

        if (in_array($mission->current_step, $etapesPdfDisponible, true)) {
            return $user->isAdmin()
                || $user->isAudit()
                || $this->estDemandeur($user, $mission)
                || $estParticipant
                || $this->peutValiderRh($user)
                || $user->isResponsableRh();
        }

        if ($mission->current_step === Mission::STEP_ATTENTE_SIGNATURE_RRH) {
            return $user->isResponsableRh() || $this->peutValiderRh($user);
        }

        return false;
    }

    private function peutVoirPdfOrdreProlongation(User $user, Mission $mission, bool $estParticipant): bool
    {
        if (! $mission->ordre_prolongation_pdf_path) {
            return false;
        }

        return $user->isAdmin()
            || $user->isAudit()
            || $this->estDemandeur($user, $mission)
            || $estParticipant
            || $this->peutValiderRh($user)
            || $user->isResponsableRh();
    }

    private function reponsePdfInline(string $content, string $filename): HttpResponse
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $missionsQuery = Mission::with(['demandeur', 'beneficiaire', 'participants']);
        $this->appliquerFiltreMissionsVisiblesUtilisateur($missionsQuery, $user);

        $statsQuery = Mission::query();
        $this->appliquerFiltreMissionsVisiblesUtilisateur($statsQuery, $user);

        $this->appliquerFiltresTableauDeBord($missionsQuery, $request);

        $missions = $missionsQuery
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $missions->getCollection()->transform(function (Mission $mission) use ($user) {
            $mission->setAttribute('is_demandeur', $mission->demandeur_id === $user->id);
            $mission->setAttribute('is_missionnaire', $this->estParticipant($user, $mission));
            $mission->setAttribute('etape_libelle', $this->libelleEtapeMission($mission));
            $mission->setAttribute('peut_modifier_demande', $this->demandeurPeutModifierDemande($user, $mission));

            return $mission;
        });

        return Inertia::render('Missions/Index', [
            'missions' => $missions,
            'isAuditMode' => $user->isAudit(),
            'authUserId' => $user->id,
            'vueTableauDeBord' => true,
            'voitToutesLesMissions' => $this->utilisateurAVisibiliteMissionsComplete($user),
            'statsMissions' => [
                'total' => (clone $statsQuery)->count(),
                'en_cours' => (clone $statsQuery)->where('status', 'en_cours')->count(),
                'cloturees' => (clone $statsQuery)->where('current_step', Mission::STEP_CLOTUREE)->count(),
            ],
            'sitesPopulaires' => MissionSites::statsSitesPopulaires($statsQuery, 3),
            'sitesCatalog' => MissionSites::catalog(),
            'filtreNumero' => $this->filtreNumeroMissionPourVue($request),
            'filtreDemandeur' => $this->filtreDemandeurMissionPourVue($request),
        ]);
    }

    public function espaceMissionnaire(Request $request): RedirectResponse
    {
        return redirect()->route('missions.traitees-recap');
    }

    public function recapMissionsTraitees(Request $request): Response
    {
        $user = $request->user();
        $periode = $request->query('periode', 'mois');

        return Inertia::render('Missions/RecapMissionsTraitees', [
            'recap' => $this->construireRecapMissionsTraitees($user, $periode),
            'periode' => in_array($periode, ['semaine', 'mois', 'annee'], true) ? $periode : 'mois',
            'activeTab' => 'recap',
            'filtreNumero' => $this->filtreNumeroMissionPourVue($request),
            'peutVoirMontantsRecap' => $user->peutVoirRecapLogistique(),
        ]);
    }

    public function vueMissionsTraitees(Request $request): Response
    {
        $user = $request->user();
        $idsHistoriqueValidees = $this->idsMissionsHistoriqueValideesParUtilisateur($user);

        $missionsQuery = Mission::with(['demandeur', 'beneficiaire', 'logs' => fn ($q) => $q->latest()->limit(1)]);
        $this->appliquerFiltreMissionsVisiblesUtilisateur($missionsQuery, $user);

        $this->appliquerFiltreNumeroMission($missionsQuery, $request);

        $missions = $missionsQuery
            ->where(function ($query) use ($user, $idsHistoriqueValidees) {
                $query->where('current_step', Mission::STEP_CLOTUREE)
                    ->orWhere('status', 'rejete');

                if ($idsHistoriqueValidees !== []) {
                    $query->orWhereIn('id', $idsHistoriqueValidees);
                }

                if ($user->isFinance()) {
                    $query->orWhere('finance_logistique_validee_par', $user->id);
                }
            })
            ->orderByRaw('CASE WHEN current_step = ? THEN 0 ELSE 1 END', [Mission::STEP_CLOTUREE])
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $this->appliquerEnrichissementListeValidation($missions, $user);

        return Inertia::render('Missions/Traitees', [
            'missions' => $missions,
            'activeTab' => 'liste',
            'filtreNumero' => $this->filtreNumeroMissionPourVue($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Missions/Create', [
            'collaborateurs' => $this->listeCollaborateursMission($user),
            'selectionMissionnairesIllimitee' => $this->demandeurPeutSelectionnerTousMissionnaires($user),
            'demandeur' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'sitesCatalog' => MissionSites::catalog(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $action = $request->input('action', 'soumettre');
        $estBrouillon = $action === 'brouillon';

        $validated = $request->validate($this->reglesMission(! $estBrouillon));

        $profilIds = $validated['participant_profil_ids'];
        $this->asserterMissionnairesAutorises($user, $profilIds);

        $n1Id = $this->resoudreN1ValidateurId($user);
        $beneficiaireId = $this->premierUserIdDepuisProfils($profilIds);
        $sitesPayload = $this->preparerSitesMissionPayload($validated['sites_mission']);
        $descriptionsSites = $this->validerDescriptionsSites(
            $validated['sites_mission'],
            $validated['descriptions_sites'] ?? [],
        );
        DB::beginTransaction();
        try {
            $mission = Mission::create([
                'demandeur_id' => $user->id,
                'beneficiaire_id' => $beneficiaireId ?? $user->id,
                'n2_beneficiaire_id' => $n1Id,
                'objet' => $validated['objet'],
                'description' => $validated['description'],
                'descriptions_sites' => $descriptionsSites,
                'perimetre' => $sitesPayload['perimetre'],
                'sites_mission' => $sitesPayload['sites_mission'],
                'priorite' => $validated['priorite'],
                'date_debut' => $validated['date_debut'],
                'date_fin' => $validated['date_fin'],
                'budget' => null,
                'current_step' => $estBrouillon ? Mission::STEP_BROUILLON : Mission::STEP_ATTENTE_N1,
                'status' => $estBrouillon ? 'brouillon' : 'en_cours',
            ]);

            $this->syncMissionnairesFromProfilIds($mission, $profilIds);

            if (! $estBrouillon) {
                $this->attribuerNumeroMissionSiNecessaire($mission);
            }

            MissionLog::create([
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'action' => $estBrouillon ? 'brouillon' : 'soumission',
                'etape_concernee' => 'Niveau 1 — Création',
                'commentaire' => $estBrouillon
                    ? 'Mission enregistrée en brouillon.'
                    : 'Demande soumise pour validation N+1.',
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Échec création mission', ['error' => $e->getMessage(), 'user_id' => $user->id]);

            return redirect()->back()->withInput()->with('error', 'Une erreur est survenue lors de l’enregistrement. Vérifiez les missionnaires sélectionnés et réessayez.');
        }

        if (! $estBrouillon) {
            try {
                $this->notifierEtapeCourante(
                    $mission,
                    'Vous avez été désigné(e) missionnaire sur une nouvelle demande soumise pour validation.',
                );
            } catch (\Throwable $e) {
                Log::warning('Notifications mission non envoyées après création', [
                    'mission_id' => $mission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = $estBrouillon
            ? 'La mission a été enregistrée en brouillon.'
            : 'La demande a été soumise pour validation N+1.';

        return redirect()->route('missions.index')->with('success', $message);
    }

    public function show(Request $request, Mission $mission): Response
    {
        $user = $request->user();

        $peutVoirHistorique = $this->peutVoirHistoriqueMission($user);
        $relations = ['demandeur', 'beneficiaire', 'participants', 'chauffeur', 'n1Validateur', 'rapportPiecesJointes'];
        if ($peutVoirHistorique) {
            $relations[] = 'logs.auteur';
        }
        $mission->load($relations);

        $estParticipant = $this->estParticipant($user, $mission);

        $peutConsulter = $this->missionVisiblePourUtilisateur($user, $mission);

        if (! $peutConsulter) {
            abort(403, 'Vous n’avez pas accès à cette mission.');
        }

        $peutTraiter = $this->peutTraiterEtapeCourante($user, $mission);
        $consultationSeule = ($estParticipant && ! $this->estDemandeur($user, $mission)) || $user->isAudit();

        $afficherDetailsLogistique = $this->peutVoirTousDetailsLogistique($user);
        $afficherCommentaireFacilities = ($user->isLogistique() || $user->isFacilities()) && ! $peutVoirHistorique;

        $missionData = $mission->toArray();
        if (! $afficherDetailsLogistique) {
            unset(
                $missionData['total_logistique'],
                $missionData['prix_carburant_estime'],
                $missionData['prix_transport_estime'],
                $missionData['prix_logement_estime'],
                $missionData['autres_frais_logistique'],
            );
        }
        if (! $afficherCommentaireFacilities) {
            unset($missionData['commentaire_facilities']);
        }
        $missionData['participants'] = $this->formaterMissionnairesPourAffichage(
            $this->missionnairesMission($mission),
            $user,
        );
        if (! $peutVoirHistorique) {
            unset($missionData['logs']);
        }
        unset($missionData['rapport_pieces_jointes']);
        $missionData['sites_mission'] = $this->sitesMissionPourFormulaire($mission);
        $missionData['rapport_pieces_jointes'] = $mission->rapportPiecesJointes
            ->map(fn (MissionRapportPieceJointe $piece) => [
                'id' => $piece->id,
                'nom_fichier' => $piece->nom_fichier,
                'mime_type' => $piece->mime_type,
                'taille' => $piece->taille,
            ])
            ->values()
            ->all();

        return Inertia::render('Missions/Show', [
            'mission' => $missionData,
            'chauffeurs' => [],
            'afficherDetailsLogistique' => $afficherDetailsLogistique,
            'afficherCommentaireFacilities' => $afficherCommentaireFacilities,
            'authUserId' => $user->id,
            'isOwner' => $this->estDemandeur($user, $mission),
            'canEditDemande' => $this->demandeurPeutModifierDemande($user, $mission),
            'canModifierDemandeN1' => $this->n1PeutModifierDemande($user, $mission),
            'isParticipant' => $estParticipant,
            'isConsultationSeule' => $consultationSeule,
            'isN1' => $this->estN1Validateur($user, $mission),
            'isAudit' => $user->isAudit(),
            'canValidateN1' => $mission->current_step === Mission::STEP_ATTENTE_N1 && $peutTraiter,
            'canValidateDga' => $mission->current_step === Mission::STEP_ATTENTE_DGA && $peutTraiter && $user->isDga(),
            'validationN1EtDgaCombinee' => $this->dgaValideN1EtDgaCombine($mission, $user),
            'canValidateMd' => $mission->current_step === Mission::STEP_ATTENTE_MD && $peutTraiter && $user->isMd(),
            'canPrintFicheValidation' => $this->peutImprimerFicheValidation($user, $mission),
            'canValidateFacilities' => $mission->current_step === Mission::STEP_ATTENTE_FACILITIES && $peutTraiter,
            'canValidateRhLogistique' => $this->estEtapeRh($mission) && $peutTraiter && $this->peutValiderRh($user),
            'canPreviewOrdre' => $this->estEtapeRh($mission) && $peutTraiter && $this->peutValiderRh($user),
            'canPreviewOrdreProlongation' => $this->estEtapeRh($mission) && $peutTraiter && $this->peutValiderRh($user)
                && $this->estProlongationEnAttenteSignature($mission),
            'canSignerRrh' => $mission->current_step === Mission::STEP_ATTENTE_SIGNATURE_RRH && $peutTraiter && $user->isResponsableRh(),
            'canActionner' => $peutTraiter && ! $user->isAudit() && ! ($estParticipant && ! $this->estDemandeur($user, $mission)),
            'canDownloadPdf' => $this->peutTelechargerPdfOrdre($user, $mission, $estParticipant),
            'canVoirOrdreProlongationPdf' => $this->peutVoirPdfOrdreProlongation($user, $mission, $estParticipant),
            'prolongationEnCours' => $this->estProlongationEnAttenteSignature($mission),
            'canVoirHistorique' => $peutVoirHistorique,
            'canSoumettreRapport' => $mission->current_step === Mission::STEP_ATTENTE_RAPPORT
                && $this->estMissionnaire($user, $mission)
                && $mission->rapport_soumis_at === null,
            'canValiderRapport' => $mission->current_step === Mission::STEP_ATTENTE_VALIDATION_RAPPORT
                && $this->estDemandeur($user, $mission),
            'canModifierDuree' => $this->peutModifierDureeMission($user, $mission),
            'canValiderFinance' => $this->peutValiderLogistiqueFinance($user, $mission),
            'financeLogistiqueValidee' => $mission->finance_logistique_validee_at !== null,
            'canVoirRapportPdf' => $mission->rapport_pdf_path !== null
                && ($this->estDemandeur($user, $mission) || $estParticipant || $user->isAdmin() || $user->isAudit()),
            'sitesCatalog' => MissionSites::catalog(),
            'signataireNomDefaut' => $this->nomSignatairePourUser($user),
            'rapportSections' => MissionRapport::sections(),
            'rapportSectionsSoumises' => MissionRapport::sectionsAffichables($mission->rapport_reponses),
        ]);
    }

    /** Niveau 2 — Validation N+1 */
    public function valider(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        if ($mission->current_step !== Mission::STEP_ATTENTE_N1 || ! $this->peutTraiterEtapeCourante($user, $mission)) {
            abort(403, 'Cette mission n’est plus à l’étape N+1 ou vous n’êtes pas habilité à la traiter.');
        }

        $validationCombinee = $this->dgaValideN1EtDgaCombine($mission, $user);

        $validated = $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'signature' => $validationCombinee
                ? ['required', 'string', 'max:500000', 'regex:/^data:image\/(png|jpe?g|webp);base64,/']
                : $this->reglesSignatureMission(),
        ]);

        $signature = $validated['signature'] ?? null;
        $commentaire = $validated['commentaire']
            ?? ($validationCombinee ? 'Validation N+1 et DGA combinée (DGA = N+1).' : 'Validation N+1 accordée.');

        DB::beginTransaction();
        try {
            $this->enregistrerLogValidation(
                $mission,
                $user,
                'Niveau 2 — Validation N+1',
                $signature,
                $validationCombinee ? 'Validation N+1 et DGA combinée (DGA = N+1).' : $commentaire,
            );

            if ($user->isMd()) {
                $this->enregistrerLogValidation(
                    $mission,
                    $user,
                    'Niveau 2c — Validation DG (MD)',
                    $signature,
                    'Validation et signature DG (N+1 = DG).',
                );
                $mission->update([
                    'current_step' => Mission::STEP_ATTENTE_FACILITIES,
                    'dga_contournee' => true,
                    'md_signe_at' => now(),
                ]);
                $this->notifierEtapeCourante($mission);
                $message = 'Validation et signature DG enregistrées. Facilities a été notifié.';
            } elseif ($user->isDga()) {
                $this->enregistrerLogValidation(
                    $mission,
                    $user,
                    'Niveau 2b — Validation DGA',
                    $signature,
                    'Validation N+1 et DGA combinée (DGA = N+1).',
                );
                $mission->update([
                    'current_step' => Mission::STEP_ATTENTE_MD,
                    'dga_contournee' => true,
                ]);
                $this->notifierEtapeCourante($mission);
                $message = 'Validation N+1 et DGA enregistrées avec une seule signature. Le DG a été notifié.';
            } else {
                $mission->update([
                    'current_step' => Mission::STEP_ATTENTE_DGA,
                    'dga_contournee' => false,
                ]);
                $this->notifierEtapeCourante($mission);
                $message = 'Validation N+1 enregistrée. La DGA a été notifiée.';
            }

            DB::commit();

            return $this->redirectApresActionEtape(Mission::STEP_ATTENTE_N1, 'success', $message, $user);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_N1,
                'error',
                'Une erreur technique est survenue.',
                $user,
            );
        }
    }

    /** Validation DGA avec signature électronique */
    public function validerDga(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        if ($mission->current_step !== Mission::STEP_ATTENTE_DGA || ! $user->isDga()) {
            abort(403, 'Cette mission n’est plus à l’étape DGA ou vous n’êtes pas habilité à la traiter.');
        }

        $validated = $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'signature' => ['required', 'string', 'max:500000', 'regex:/^data:image\/(png|jpe?g|webp);base64,/'],
        ]);

        $validationCombinee = $this->estDemandeur($user, $mission);
        $commentaireCombine = 'Validation N+1 et DGA combinée (DGA = demandeur).';

        DB::beginTransaction();
        try {
            if ($validationCombinee) {
                $this->enregistrerLogValidation(
                    $mission,
                    $user,
                    'Niveau 2 — Validation N+1',
                    $validated['signature'],
                    $commentaireCombine,
                );
            }

            $this->enregistrerLogValidation(
                $mission,
                $user,
                'Niveau 2b — Validation DGA',
                $validated['signature'],
                $validationCombinee
                    ? $commentaireCombine
                    : ($validated['commentaire'] ?? 'Validation DGA accordée.'),
            );

            $mission->update(['current_step' => Mission::STEP_ATTENTE_MD]);
            $this->notifierEtapeCourante($mission);

            DB::commit();

            $message = $validationCombinee
                ? 'Validation N+1 et DGA enregistrées avec une seule signature. Le DG a été notifié.'
                : 'Demande validée.';

            return $this->redirectApresActionEtape(Mission::STEP_ATTENTE_DGA, 'success', $message, $user);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_DGA,
                'error',
                'Une erreur technique est survenue.',
                $user,
            );
        }
    }

    /** Validation DG (MD) avec signature électronique — avant envoi Facilities */
    public function validerMd(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        if ($mission->current_step !== Mission::STEP_ATTENTE_MD || ! $user->isMd()) {
            abort(403, 'Cette mission n’est plus à l’étape DG ou vous n’êtes pas habilité à la traiter.');
        }

        $validated = $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'signature' => ['required', 'string', 'max:500000', 'regex:/^data:image\/(png|jpe?g|webp);base64,/'],
        ]);

        DB::beginTransaction();
        try {
            $this->enregistrerLogValidation(
                $mission,
                $user,
                'Niveau 2c — Validation DG (MD)',
                $validated['signature'],
                $validated['commentaire'] ?? 'Validation et signature DG accordées.',
            );

            $mission->update([
                'current_step' => Mission::STEP_ATTENTE_FACILITIES,
                'md_signe_at' => now(),
            ]);

            $this->notifierEtapeCourante($mission);

            DB::commit();

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_MD,
                'success',
                'Demande validée et transmise à Facilities.',
                $user,
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_MD,
                'error',
                'Une erreur technique est survenue.',
                $user,
            );
        }
    }

    /** Niveau 3 — Facilities (logistique par chauffeur et transport autonome) */
    public function marquerPriseEnChargeFacilities(Request $request, Mission $mission): RedirectResponse
    {
        if ($mission->current_step !== Mission::STEP_ATTENTE_FACILITIES || ! $this->peutTraiterEtapeCourante($request->user(), $mission)) {
            abort(403, 'Cette mission n’est plus à l’étape Facilities ou vous n’êtes pas habilité à la traiter.');
        }

        $missionnaires = $this->missionnairesMission($mission);
        $participantIds = $missionnaires->pluck('id')->all();
        $nbMissionnaires = count($participantIds);

        if ($nbMissionnaires === 0) {
            throw ValidationException::withMessages([
                'missionnaires_autonomes' => 'Aucun missionnaire n’est associé à cette mission.',
            ]);
        }

        $regles = [
            'chauffeurs_logistique' => ['present', 'array'],
            'missionnaires_autonomes' => ['required', 'array', 'size:'.$nbMissionnaires],
            'missionnaires_autonomes.*.participant_id' => ['required', 'integer', Rule::in($participantIds)],
            'missionnaires_autonomes.*.logement' => ['nullable', 'string', 'max:255'],
            'missionnaires_autonomes.*.per_diem' => ['nullable', 'numeric', 'min:0'],
            'missionnaires_autonomes.*.prix_transport' => ['nullable', 'numeric', 'min:0'],
            'missionnaires_autonomes.*.prix_logement' => ['nullable', 'numeric', 'min:0'],
            'missionnaires_autonomes.*.autres_frais' => ['nullable', 'numeric', 'min:0'],
            'missionnaires_autonomes.*.logistique_sites' => ['nullable', 'array'],
            'missionnaires_autonomes.*.logistique_sites.*.site' => ['required', 'string'],
            'missionnaires_autonomes.*.logistique_sites.*.nuits' => ['nullable', 'integer', 'min:0'],
            'missionnaires_autonomes.*.logistique_sites.*.prix_nuit' => ['nullable', 'numeric', 'min:0'],
            'missionnaires_autonomes.*.logistique_sites.*.jours' => ['nullable', 'integer', 'min:0'],
            'missionnaires_autonomes.*.logistique_sites.*.prix_journalier' => ['nullable', 'numeric', 'min:0'],
            'missionnaires_autonomes.*.logistique_sites.*.phase' => ['nullable', 'string'],
            'missionnaires_autonomes.*.logistique_sites.*.verrouille' => ['nullable', 'boolean'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ];

        if ($request->input('chauffeurs_logistique', []) !== []) {
            $regles += [
                'chauffeurs_logistique.*.chauffeur_selection' => ['required', 'string', 'regex:/^(user|profil)-\d+$/'],
                'chauffeurs_logistique.*.participant_ids' => ['required', 'array', 'min:1'],
                'chauffeurs_logistique.*.participant_ids.*' => ['integer', Rule::in($participantIds)],
                'chauffeurs_logistique.*.vehicule' => ['nullable', 'string', 'max:255'],
                'chauffeurs_logistique.*.logement' => ['nullable', 'string', 'max:255'],
                'chauffeurs_logistique.*.per_diem' => ['nullable', 'numeric', 'min:0'],
                'chauffeurs_logistique.*.prix_carburant' => ['nullable', 'numeric', 'min:0'],
                'chauffeurs_logistique.*.prix_logement' => ['nullable', 'numeric', 'min:0'],
                'chauffeurs_logistique.*.autres_frais' => ['nullable', 'numeric', 'min:0'],
            ];
        }

        $validated = $request->validate($regles);

        $assignations = collect($validated['chauffeurs_logistique'])
            ->flatMap(fn (array $bloc) => collect($bloc['participant_ids'])->map(fn ($id) => (int) $id));

        $autonomesIds = collect($validated['missionnaires_autonomes'])->pluck('participant_id')->map(fn ($id) => (int) $id);

        if ($assignations->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'chauffeurs_logistique' => 'Un missionnaire ne peut être attribué qu’à un seul chauffeur.',
            ]);
        }

        $fraisIds = $autonomesIds->unique()->sort()->values();
        if ($fraisIds->all() !== collect($participantIds)->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'missionnaires_autonomes' => 'Chaque missionnaire doit avoir une ligne de frais.',
            ]);
        }

        $selectionsChauffeur = collect($validated['chauffeurs_logistique'])->pluck('chauffeur_selection');
        if ($selectionsChauffeur->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'chauffeurs_logistique' => 'Chaque chauffeur ne peut être attribué qu’une seule fois.',
            ]);
        }

        $totalMission = $this->calculerTotalLogistiqueFacilities(
            $validated['chauffeurs_logistique'],
            $validated['missionnaires_autonomes'],
        );
        $besoinChauffeurMission = $validated['chauffeurs_logistique'] !== [];

        $fraisParParticipant = collect($validated['missionnaires_autonomes'])->keyBy('participant_id');

        DB::beginTransaction();
        try {
            $chauffeursAttribues = [];

            foreach ($validated['missionnaires_autonomes'] as $ligne) {
                if ($assignations->contains((int) $ligne['participant_id'])) {
                    continue;
                }

                $participant = MissionParticipant::query()
                    ->where('mission_id', $mission->id)
                    ->where('id', $ligne['participant_id'])
                    ->firstOrFail();

                $sitesTotaux = $this->fusionnerLogistiqueSitesParticipant($participant, $ligne['logistique_sites'] ?? []);

                $participant->update([
                    'vehicule' => null,
                    'logement' => $ligne['logement'] ?? null,
                    'per_diem' => $sitesTotaux['per_diem'],
                    'prix_carburant' => 0,
                    'prix_transport' => 0,
                    'prix_logement' => $sitesTotaux['prix_logement'],
                    'autres_frais' => $ligne['autres_frais'] ?? 0,
                    'logistique_sites' => $sitesTotaux['lignes'],
                    'besoin_chauffeur' => false,
                    'chauffeur_id' => null,
                    'chauffeur_profil_id' => null,
                ]);
            }

            foreach ($validated['chauffeurs_logistique'] as $bloc) {
                $chauffeurResolu = $this->resoudreSelectionChauffeur((string) $bloc['chauffeur_selection']);
                $chauffeurId = $chauffeurResolu['chauffeur_id'];
                $chauffeurProfilId = $chauffeurResolu['chauffeur_profil_id'];
                $participantIdsBloc = collect($bloc['participant_ids'])->map(fn ($id) => (int) $id)->sort()->values();
                $premier = true;

                foreach ($participantIdsBloc as $participantId) {
                    $frais = $fraisParParticipant->get($participantId, []);
                    $participant = MissionParticipant::query()
                        ->where('mission_id', $mission->id)
                        ->where('id', $participantId)
                        ->firstOrFail();

                    $sitesTotaux = $this->fusionnerLogistiqueSitesParticipant($participant, $frais['logistique_sites'] ?? []);

                    $participant->update([
                        'vehicule' => $premier ? ($bloc['vehicule'] ?? null) : null,
                        'logement' => $frais['logement'] ?? ($premier ? ($bloc['logement'] ?? null) : null),
                        'per_diem' => $sitesTotaux['per_diem'] + ($premier ? (float) ($bloc['per_diem'] ?? 0) : 0),
                        'prix_carburant' => $premier ? ($bloc['prix_carburant'] ?? 0) : 0,
                        'prix_transport' => 0,
                        'prix_logement' => $sitesTotaux['prix_logement'] + ($premier ? (float) ($bloc['prix_logement'] ?? 0) : 0),
                        'autres_frais' => (float) ($frais['autres_frais'] ?? 0) + ($premier ? (float) ($bloc['autres_frais'] ?? 0) : 0),
                        'logistique_sites' => $sitesTotaux['lignes'],
                        'besoin_chauffeur' => true,
                        'chauffeur_id' => $chauffeurId,
                        'chauffeur_profil_id' => $chauffeurProfilId,
                    ]);

                    $premier = false;
                }

                if ($chauffeurId) {
                    $chauffeursAttribues[$chauffeurId] = ['role_dans_mission' => 'chauffeur'];
                } elseif ($chauffeurProfilId) {
                    MissionParticipant::query()->updateOrCreate(
                        [
                            'mission_id' => $mission->id,
                            'profil_id' => $chauffeurProfilId,
                            'role_dans_mission' => 'chauffeur',
                        ],
                        ['user_id' => null],
                    );
                }
            }

            if ($chauffeursAttribues !== []) {
                $mission->participants()->syncWithoutDetaching($chauffeursAttribues);
            }

            $premierChauffeurId = collect($validated['chauffeurs_logistique'])
                ->map(fn (array $bloc) => $this->resoudreSelectionChauffeur((string) $bloc['chauffeur_selection'])['chauffeur_id'])
                ->filter()
                ->first();

            $mission->update([
                'besoin_chauffeur' => $besoinChauffeurMission,
                'chauffeur_id' => $premierChauffeurId,
                'commentaire_facilities' => $validated['commentaire'] ?? null,
                'total_logistique' => $totalMission,
                'current_step' => Mission::STEP_ATTENTE_RH,
                'finance_logistique_validee_at' => null,
                'finance_logistique_validee_par' => null,
            ]);

            MissionLog::create([
                'mission_id' => $mission->id,
                'user_id' => $request->user()->id,
                'action' => 'attribution_facilities',
                'etape_concernee' => 'Niveau 3 — Facilities',
                'commentaire' => sprintf(
                    'Logistique renseignée pour %d missionnaire(s).%s%s',
                    $missionnaires->count(),
                    $besoinChauffeurMission ? ' Chauffeur(s) attribué(s) par Facilities.' : '',
                    $validated['commentaire'] ? ' ' . $validated['commentaire'] : ''
                ),
            ]);

            DB::commit();

            $this->notifierEtapeCourante($mission->fresh());

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_FACILITIES,
                'success',
                'Logistique enregistrée. La RH a été notifiée pour validation et génération des ordres de mission.',
                $request->user(),
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Échec validation Facilities', [
                'mission_id' => $mission->id,
                'message' => $e->getMessage(),
            ]);

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_FACILITIES,
                'error',
                'Une erreur est survenue lors de l\'enregistrement de la logistique.',
                $request->user(),
            );
        }
    }

    /** Niveau 4 — Validation RH (génération des ordres de mission) */
    public function validerRhLogistique(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        if (! $this->peutValiderRh($user)) {
            abort(403, 'Accès réservé au personnel des Ressources Humaines.');
        }

        if (! $this->estEtapeRh($mission) || ! $this->peutTraiterEtapeCourante($user, $mission)) {
            abort(403, 'Cette mission n’est plus à l’étape de validation RH.');
        }

        $validated = $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        $missionnairesSansChauffeur = $this->missionnairesMission($mission)
            ->filter(fn (MissionParticipant $p) => $p->besoin_chauffeur && ! $p->chauffeur_id && ! $p->chauffeur_profil_id);

        if ($missionnairesSansChauffeur->isNotEmpty()) {
            throw ValidationException::withMessages([
                'commentaire' => 'Des chauffeurs manquent : renvoyez la mission à Facilities pour compléter l\'attribution.',
            ]);
        }

        DB::beginTransaction();
        try {
            $mission->load(['participants', 'chauffeur']);
            $estProlongation = $this->estProlongationEnAttenteSignature($mission);

            if ($estProlongation) {
                if ($mission->finance_logistique_validee_at === null) {
                    throw ValidationException::withMessages([
                        'commentaire' => 'La validation Finance des nouvelles dépenses est requise avant de générer l\'ordre de prolongation.',
                    ]);
                }

                $pdf = $this->construirePdfOrdreProlongation($mission);
                $filename = "missions/ordre_prolongation_{$mission->id}_" . now()->format('Y-m-d_His') . '.pdf';
                Storage::disk('local')->put($filename, $pdf['content']);

                $mission->update([
                    'current_step' => Mission::STEP_ATTENTE_SIGNATURE_RRH,
                    'ordre_prolongation_pdf_path' => $filename,
                ]);

                MissionLog::create([
                    'mission_id' => $mission->id,
                    'user_id' => $user->id,
                    'action' => 'generation_ordre_prolongation',
                    'etape_concernee' => 'Niveau 4 — Validation RH (prolongation)',
                    'commentaire' => $validated['commentaire'] ?? 'Ordre de prolongation généré et transmis pour signature électronique du Responsable RH.',
                    'signature_hash' => $pdf['hash'],
                ]);
            } else {
                $pdf = $this->construirePdfOrdreMission($mission);
                $filename = "missions/ordre_mission_{$mission->id}_" . now()->format('Y-m-d_His') . '.pdf';
                Storage::disk('local')->put($filename, $pdf['content']);

                $mission->update([
                    'current_step' => Mission::STEP_ATTENTE_SIGNATURE_RRH,
                    'pdf_path' => $filename,
                ]);

                MissionLog::create([
                    'mission_id' => $mission->id,
                    'user_id' => $user->id,
                    'action' => 'generation_ordre_mission',
                    'etape_concernee' => 'Niveau 4 — Validation RH',
                    'commentaire' => $validated['commentaire'] ?? 'Ordre(s) de mission généré(s) et transmis pour signature électronique du Responsable RH.',
                    'signature_hash' => $pdf['hash'],
                ]);
            }

            MissionNotificationService::notifyResponsableRhPourSignature($mission, $user);
            MissionNotificationService::notifyDemandeurChangementEtape(
                $mission,
                $this->libelleEtapeMission($mission),
            );
            $mission->update(['last_reminder_at' => null]);

            DB::commit();

            $messageSucces = $estProlongation
                ? 'Ordre de prolongation généré. Le Responsable RH a été notifié pour signature électronique.'
                : 'Ordre(s) de mission généré(s). Le Responsable RH a été notifié pour signature électronique.';

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_RH,
                'success',
                $messageSucces,
                $user,
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_RH,
                'error',
                'Une erreur est survenue lors de la génération des ordres.',
                $user,
            );
        }
    }

    /** Niveau 5 — Signature Responsable RH des ordres de mission */
    public function signerOrdreRrh(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isResponsableRh()) {
            abort(403, 'Accès réservé au Responsable des Ressources Humaines.');
        }

        if ($mission->current_step !== Mission::STEP_ATTENTE_SIGNATURE_RRH || ! $this->peutTraiterEtapeCourante($user, $mission)) {
            abort(403, 'Cette mission n\'est plus en attente de votre signature.');
        }

        $estProlongation = $this->estProlongationEnAttenteSignature($mission);

        if ($estProlongation) {
            if (! $mission->ordre_prolongation_pdf_path) {
                throw ValidationException::withMessages([
                    'signature' => 'Aucun ordre de prolongation n\'a été généré par la RH.',
                ]);
            }
        } elseif (! $mission->pdf_path) {
            throw ValidationException::withMessages([
                'signature' => 'Aucun ordre de mission n\'a été généré par la RH.',
            ]);
        }

        $validated = $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'signature' => ['required', 'string', 'max:500000', 'regex:/^data:image\/(png|jpe?g|webp);base64,/'],
        ]);

        $signature = $this->optimiserSignatureImage($validated['signature']);

        DB::beginTransaction();
        try {
            if ($estProlongation) {
                $this->enregistrerLogValidation(
                    $mission,
                    $user,
                    'Signature Responsable RH — Ordre de prolongation',
                    $validated['signature'],
                    $validated['commentaire'] ?? 'Ordre de prolongation signé électroniquement par le Responsable RH.',
                );

                $filename = $this->genererPdfOrdreProlongation($mission, $signature, $user);
                $etapeReprise = $mission->etape_reprise_apres_prolongation ?? Mission::STEP_VALIDEE;

                $mission->update([
                    'current_step' => $etapeReprise,
                    'status' => 'valide',
                    'ordre_prolongation_pdf_path' => $filename,
                    'ordre_prolongation_signe_at' => now(),
                    'prolongation_donnees' => null,
                    'etape_reprise_apres_prolongation' => null,
                ]);

                $this->notifierEtapeCourante(
                    $mission,
                    'L\'ordre de prolongation a été signé. La mission reprend son cours normal.',
                );

                DB::commit();

                return $this->redirectApresActionEtape(
                    Mission::STEP_ATTENTE_SIGNATURE_RRH,
                    'success',
                    'Ordre de prolongation signé. La mission reprend à l\'étape « ' . $this->libelleEtapeMission($mission->fresh()) . ' ».',
                    $user,
                );
            }

            $this->enregistrerLogValidation(
                $mission,
                $user,
                'Niveau 5 — Signature Responsable RH',
                $validated['signature'],
                $validated['commentaire'] ?? 'Ordre(s) de mission signé(s) électroniquement par le Responsable RH.',
            );

            $filename = $this->genererPdfMission($mission, $signature, $user);

            $mission->update([
                'current_step' => Mission::STEP_ATTENTE_RAPPORT,
                'status' => 'valide',
                'pdf_path' => $filename,
            ]);

            $mission->load('participants');
            MissionNotificationService::notifyOrdresGeneres($mission);
            $this->notifierEtapeCourante(
                $mission,
                'Votre ordre de mission signé est disponible. Veuillez soumettre votre rapport de mission signé.',
            );

            DB::commit();

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_SIGNATURE_RRH,
                'success',
                'Ordre(s) de mission signé(s). Les missionnaires et chauffeurs concernés ont été notifiés.',
                $user,
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Échec signature RRH mission #' . $mission->id, [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_SIGNATURE_RRH,
                'error',
                'Une erreur est survenue lors de la signature.',
                $user,
            );
        }
    }

    public function renvoyer(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'commentaire' => ['required', 'string', 'min:5', 'max:1000'],
            'destination_renvoi' => ['nullable', 'in:facilities,demandeur'],
        ]);

        if ($this->peutValiderLogistiqueFinance($user, $mission)) {
            return $this->executerRenvoiFinanceLogistique($mission, $user, $validated);
        }

        if (! $this->peutTraiterEtapeCourante($user, $mission)) {
            abort(403, 'Cette mission n’est plus à votre étape de validation.');
        }

        $etapeActuelle = $mission->current_step;

        if ($this->estEtapeRh($mission)) {
            $destination = $validated['destination_renvoi'] ?? 'facilities';
            $etapePrecedente = $destination === 'demandeur'
                ? Mission::STEP_BROUILLON
                : Mission::STEP_ATTENTE_FACILITIES;
            $libelleDestination = $destination === 'demandeur' ? 'demandeur' : 'Facilities';
        } elseif (in_array($etapeActuelle, $this->etapesAvantRh(), true)) {
            $etapePrecedente = Mission::STEP_BROUILLON;
            $libelleDestination = 'demandeur';
        } else {
            $etapePrecedente = null;
            $libelleDestination = 'étape précédente';
        }

        if (! $etapePrecedente) {
            return redirect()->route('missions.index')->with('error', 'Impossible de renvoyer depuis cette étape.');
        }

        $status = $etapePrecedente === Mission::STEP_BROUILLON ? 'renvoye' : 'en_cours';

        DB::beginTransaction();
        try {
            if ($etapePrecedente === Mission::STEP_BROUILLON && in_array($etapeActuelle, $this->etapesAvantRh(), true)) {
                $updates = $this->renvoyerAuDemandeur($mission);
            } else {
                $updates = [
                    'current_step' => $etapePrecedente,
                    'status' => $status,
                ];
                if (in_array($etapePrecedente, [
                    Mission::STEP_BROUILLON,
                    Mission::STEP_ATTENTE_N1,
                    Mission::STEP_ATTENTE_DGA,
                    Mission::STEP_ATTENTE_MD,
                ], true)) {
                    $updates['md_signe_at'] = null;
                }
            }
            $mission->update($updates);

            MissionLog::create([
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'action' => 'renvoi',
                'etape_concernee' => $etapeActuelle,
                'commentaire' => sprintf(
                    'Renvoyé vers %s — %s',
                    $libelleDestination,
                    $validated['commentaire'],
                ),
            ]);

            $mission->load(['participants', 'demandeur']);
            $this->notifierParticipants($mission, 'La demande a été renvoyée pour correction : '.$validated['commentaire']);

            $this->notifierEtapeCourante($mission);

            DB::commit();

            $message = $etapePrecedente === Mission::STEP_BROUILLON
                ? 'La demande a été renvoyée au demandeur pour correction.'
                : 'La mission a été renvoyée à Facilities pour correction.';

            return $this->redirectApresActionEtape($etapeActuelle, 'success', $message, $user);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->redirectApresActionEtape($etapeActuelle, 'error', 'Une erreur technique est survenue.', $user);
        }
    }

    public function rejeter(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        if (! $this->peutTraiterEtapeCourante($user, $mission)) {
            abort(403, 'Cette mission n’est plus à votre étape de validation.');
        }

        $validated = $request->validate([
            'commentaire' => ['required', 'string', 'min:5'],
        ]);

        $etapeActuelle = $mission->current_step;

        DB::beginTransaction();
        try {
            $mission->update(['status' => 'rejete']);

            MissionLog::create([
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'action' => 'rejet',
                'etape_concernee' => $etapeActuelle,
                'commentaire' => $validated['commentaire'],
            ]);

            $mission->load('participants');
            $this->notifierParticipants($mission, 'La demande a été rejetée définitivement.');

            DB::commit();

            return $this->redirectApresActionEtape($etapeActuelle, 'success', 'La mission a été définitivement rejetée.', $user);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->redirectApresActionEtape($etapeActuelle, 'error', 'Une erreur est survenue.', $user);
        }
    }

    public function vueRh(Request $request): RedirectResponse
    {
        return redirect()->route('missions.validation-rh-logistique');
    }

    public function vueSignatureRrh(Request $request): Response
    {
        $user = $request->user();

        if (! $this->peutSignerRrh($user)) {
            abort(403, 'Accès réservé au Responsable des Ressources Humaines.');
        }

        $missionsQuery = Mission::with(['demandeur', 'logs' => fn ($q) => $q->latest()->limit(1)])
            ->where(function ($query) use ($user) {
                $this->contraindreMissionsFileValidation(
                    $query,
                    $user,
                    [Mission::STEP_ATTENTE_SIGNATURE_RRH],
                    fn ($q) => $q->where('status', 'en_cours'),
                );
            });

        $this->appliquerFiltreNumeroMission($missionsQuery, $request);

        $missions = $missionsQuery
            ->orderByRaw('CASE WHEN current_step = ? THEN 0 ELSE 1 END', [Mission::STEP_ATTENTE_SIGNATURE_RRH])
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $this->appliquerEnrichissementListeValidation($missions, $user);

        return Inertia::render('Missions/ValidationSignatureRrh', [
            'missions' => $missions,
            'filtreNumero' => $this->filtreNumeroMissionPourVue($request),
        ]);
    }

    public function vueRhLogistique(Request $request): Response
    {
        $user = $request->user();

        if (! $this->peutValiderRh($user)) {
            abort(403, 'Accès réservé au personnel des Ressources Humaines.');
        }

        $missionsQuery = Mission::with(['demandeur', 'logs' => fn ($q) => $q->latest()->limit(1)])
            ->where(function ($query) use ($user) {
                $this->contraindreMissionsFileValidation(
                    $query,
                    $user,
                    [Mission::STEP_ATTENTE_RH, 'ATTENTE_RH_LOGISTIQUE'],
                    fn ($q) => $q->where('status', 'en_cours'),
                );
            });

        $this->appliquerFiltreNumeroMission($missionsQuery, $request);

        $missions = $missionsQuery
            ->orderByRaw('CASE WHEN current_step IN (?, ?) THEN 0 ELSE 1 END', [Mission::STEP_ATTENTE_RH, 'ATTENTE_RH_LOGISTIQUE'])
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $missions->getCollection()->transform(function (Mission $mission) use ($user) {
            $mission->setAttribute('nb_chauffeurs_manquants', MissionParticipant::query()
                ->where('mission_id', $mission->id)
                ->where('role_dans_mission', 'missionnaire')
                ->where('besoin_chauffeur', true)
                ->whereNull('chauffeur_id')
                ->whereNull('chauffeur_profil_id')
                ->count());
            $mission->setAttribute('validation_etat', $this->determinerEtatListeValidation($mission, $user));
            $mission->setAttribute('etape_libelle', $this->libelleEtapeMission($mission));

            return $mission;
        });

        return Inertia::render('Missions/ValidationRhLogistique', [
            'missions' => $missions,
            'filtreNumero' => $this->filtreNumeroMissionPourVue($request),
        ]);
    }

    public function vueValidationN1(Request $request): RedirectResponse
    {
        return redirect()->route('missions.validation-dga');
    }

    public function vueDga(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isDga() && ! $user->estDesigneN1DunProfil()) {
            abort(403, 'Accès réservé aux validateurs N+1 ou DGA.');
        }

        $profilId = $this->trouverProfilPourUser($user)?->id;

        $missionsQuery = Mission::with(['demandeur', 'logs' => fn ($q) => $q->latest()->limit(1)])
            ->where(function ($query) use ($user, $profilId) {
                $query->where(function ($q) use ($user, $profilId) {
                    $q->where('status', 'en_cours')
                        ->where(function ($inner) use ($user, $profilId) {
                            $premierFiltre = true;

                            if ($user->isDga()) {
                                $inner->where(function ($q) use ($user) {
                                    $this->contraindreMissionsEnAttenteValidationDga($q, $user);
                                });
                                $premierFiltre = false;
                            }

                            if ($user->estDesigneN1DunProfil()) {
                                $filtreN1 = function ($q) use ($user, $profilId) {
                                    $q->where('current_step', Mission::STEP_ATTENTE_N1)
                                        ->where(function ($n1) use ($user, $profilId) {
                                            $n1->where('n2_beneficiaire_id', $user->id)
                                                ->orWhereHas('demandeur', fn ($dq) => $dq->where('manager_id', $user->id));

                                            if ($profilId !== null) {
                                                $n1->orWhereHas('demandeur', function ($dq) use ($profilId) {
                                                    $dq->whereHas('profil', fn ($pq) => $pq->where('n_plus_1_id', $profilId));
                                                });
                                            }
                                        });
                                };

                                if ($premierFiltre) {
                                    $inner->where($filtreN1);
                                } else {
                                    $inner->orWhere($filtreN1);
                                }
                            }
                        });
                });
            });

        $this->appliquerFiltreNumeroMission($missionsQuery, $request);

        $missions = $missionsQuery
            ->orderByRaw('CASE WHEN current_step IN (?, ?) THEN 0 ELSE 1 END', [Mission::STEP_ATTENTE_N1, Mission::STEP_ATTENTE_DGA])
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $missions->getCollection()->transform(function (Mission $mission) use ($user) {
            if ($mission->current_step === Mission::STEP_ATTENTE_N1) {
                $mission->setAttribute('etape_traitement', $user->isDga() ? 'N+1 et DGA' : 'N+1');
            } elseif ($mission->current_step === Mission::STEP_ATTENTE_DGA && $mission->demandeur_id === $user->id) {
                $mission->setAttribute('etape_traitement', 'N+1 et DGA');
            } elseif ($mission->current_step === Mission::STEP_ATTENTE_DGA) {
                $mission->setAttribute('etape_traitement', 'DGA');
            } else {
                $mission->setAttribute('etape_traitement', 'N+1');
            }

            return $mission;
        });

        $this->appliquerEnrichissementListeValidation($missions, $user);

        return Inertia::render('Missions/ValidationDga', [
            'missions' => $missions,
            'filtreNumero' => $this->filtreNumeroMissionPourVue($request),
        ]);
    }

    public function vueMd(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isMd()) {
            abort(403, 'Accès réservé au Directeur Général.');
        }

        $missionsQuery = Mission::with(['demandeur', 'logs' => fn ($q) => $q->latest()->limit(1)])
            ->where(function ($query) use ($user) {
                $this->contraindreMissionsFileValidation(
                    $query,
                    $user,
                    [Mission::STEP_ATTENTE_MD],
                    fn ($q) => $q->where('status', 'en_cours'),
                );
            });

        $this->appliquerFiltreNumeroMission($missionsQuery, $request);

        $missions = $missionsQuery
            ->orderByRaw('CASE WHEN current_step = ? THEN 0 ELSE 1 END', [Mission::STEP_ATTENTE_MD])
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $this->appliquerEnrichissementListeValidation($missions, $user);

        return Inertia::render('Missions/ValidationMd', [
            'missions' => $missions,
            'filtreNumero' => $this->filtreNumeroMissionPourVue($request),
        ]);
    }

    public function vueFacilities(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isLogistique()) {
            abort(403, 'Accès réservé aux profils logistique.');
        }

        $missionsQuery = Mission::with(['demandeur', 'logs' => fn ($q) => $q->latest()->limit(1)])
            ->withCount(['missionParticipants as missionnaires_count' => fn ($q) => $q->where('role_dans_mission', 'missionnaire')])
            ->where(function ($query) use ($user) {
                $this->contraindreMissionsFileValidation(
                    $query,
                    $user,
                    [Mission::STEP_ATTENTE_FACILITIES],
                    fn ($q) => $q->where('status', 'en_cours'),
                );
            });

        $this->appliquerFiltreNumeroMission($missionsQuery, $request);

        $missions = $missionsQuery
            ->orderByRaw('CASE WHEN current_step = ? THEN 0 ELSE 1 END', [Mission::STEP_ATTENTE_FACILITIES])
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $this->appliquerEnrichissementListeValidation($missions, $user);

        return Inertia::render('Missions/ValidationFacilitiesList', [
            'missions' => $missions,
            'activeTab' => 'file',
            'filtreNumero' => $this->filtreNumeroMissionPourVue($request),
        ]);
    }

    private function etapesAvantValidationFinanceLogistique(): array
    {
        return [
            Mission::STEP_BROUILLON,
            Mission::STEP_ATTENTE_N1,
            Mission::STEP_ATTENTE_DGA,
            Mission::STEP_ATTENTE_MD,
            Mission::STEP_ATTENTE_FACILITIES,
        ];
    }

    private function missionEligibleValidationFinanceLogistique(Mission $mission): bool
    {
        return $mission->total_logistique !== null
            && (float) $mission->total_logistique > 0
            && ! in_array($mission->current_step, $this->etapesAvantValidationFinanceLogistique(), true);
    }

    private function peutValiderLogistiqueFinance(User $user, Mission $mission): bool
    {
        return $user->isFinance()
            && $this->missionEligibleValidationFinanceLogistique($mission)
            && $mission->finance_logistique_validee_at === null;
    }

    /**
     * @param  array{commentaire: string, destination_renvoi?: string|null}  $validated
     */
    private function executerRenvoiFinanceLogistique(Mission $mission, User $user, array $validated): RedirectResponse
    {
        $destination = $validated['destination_renvoi'] ?? 'facilities';

        DB::beginTransaction();
        try {
            if ($destination === 'demandeur') {
                $mission->update($this->renvoyerAuDemandeur($mission));
                $libelleDestination = 'demandeur';
                $message = 'La demande a été renvoyée au demandeur pour correction.';
            } else {
                $mission->update([
                    'current_step' => Mission::STEP_ATTENTE_FACILITIES,
                    'status' => 'en_cours',
                ]);
                $libelleDestination = 'Facilities';
                $message = 'La mission a été renvoyée à Facilities pour correction logistique.';
            }

            $this->reinitialiserValidationFinanceLogistique($mission);

            MissionLog::create([
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'action' => 'renvoi',
                'etape_concernee' => 'Validation Finance — Logistique',
                'commentaire' => sprintf(
                    'Renvoyé vers %s — %s',
                    $libelleDestination,
                    $validated['commentaire'],
                ),
            ]);

            $mission->load(['participants', 'demandeur']);
            $this->notifierParticipants($mission, 'La demande a été renvoyée pour correction : '.$validated['commentaire']);
            $this->notifierEtapeCourante($mission);

            DB::commit();

            return redirect()
                ->route('missions.validation-finance')
                ->with('success', $message);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Échec renvoi Finance logistique', [
                'mission_id' => $mission->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Une erreur technique est survenue lors du renvoi.');
        }
    }

    private function reinitialiserValidationFinanceLogistique(Mission $mission): void
    {
        if ($mission->finance_logistique_validee_at === null && $mission->finance_logistique_validee_par === null) {
            return;
        }

        $mission->update([
            'finance_logistique_validee_at' => null,
            'finance_logistique_validee_par' => null,
        ]);
    }

    public function vueFinance(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isFinance()) {
            abort(403, 'Accès réservé aux profils Finance.');
        }

        $missionsQuery = Mission::with(['demandeur', 'beneficiaire', 'financeLogistiqueValidateur'])
            ->whereNotNull('total_logistique')
            ->where('total_logistique', '>', 0)
            ->whereNull('finance_logistique_validee_at')
            ->whereNotIn('current_step', $this->etapesAvantValidationFinanceLogistique());

        $this->appliquerFiltreNumeroMission($missionsQuery, $request);

        $missions = $missionsQuery
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $missions->getCollection()->transform(function (Mission $mission) {
            $mission->setAttribute('validation_etat', 'a_traiter');
            $mission->setAttribute('etape_libelle', $this->libelleEtapeMission($mission));

            return $mission;
        });

        return Inertia::render('Missions/ValidationFinance', [
            'missions' => $missions,
            'activeTab' => 'file',
            'filtreNumero' => $this->filtreNumeroMissionPourVue($request),
        ]);
    }

    public function recapLogistique(Request $request): Response
    {
        if (! $this->peutVoirRecapLogistique($request->user())) {
            abort(403, 'Accès réservé aux profils autorisés (Facilities, Finance, RH, RRH, DGA, MD).');
        }

        $context = $request->query('context', 'facilities');
        [$dateDebut, $dateFin] = $this->resoudrePlageDatesRecapLogistique($request);

        if (! in_array($context, ['facilities', 'finance'], true)) {
            $context = 'facilities';
        }

        return Inertia::render('Missions/RecapLogistique', [
            'recap' => $this->construireRecapLogistique($context, $dateDebut, $dateFin),
            'dateDebut' => $dateDebut->toDateString(),
            'dateFin' => $dateFin->toDateString(),
            'context' => $context,
            'activeTab' => 'recap',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resoudrePlageDatesRecapLogistique(Request $request): array
    {
        $defautDebut = now()->startOfMonth();
        $defautFin = now()->endOfDay();

        $dateDebut = $this->parserDateRecap($request->query('date_debut'), $defautDebut)->startOfDay();
        $dateFin = $this->parserDateRecap($request->query('date_fin'), $defautFin)->endOfDay();

        if ($dateFin->lt($dateDebut)) {
            [$dateDebut, $dateFin] = [$dateFin->copy()->startOfDay(), $dateDebut->copy()->endOfDay()];
        }

        return [$dateDebut, $dateFin];
    }

    private function parserDateRecap(mixed $valeur, Carbon $defaut): Carbon
    {
        if (! is_string($valeur) || trim($valeur) === '') {
            return $defaut->copy();
        }

        try {
            return Carbon::parse($valeur);
        } catch (\Throwable) {
            return $defaut->copy();
        }
    }

    public function validerLogistiqueFinance(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        if (! $this->peutValiderLogistiqueFinance($user, $mission)) {
            abort(403, 'Cette mission n\'est pas en attente de validation Finance.');
        }

        $validated = $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $commentaire = trim((string) ($validated['commentaire'] ?? ''));
            $apresModificationDuree = $mission->duree_modifiee_at !== null;

            $mission->update([
                'finance_logistique_validee_at' => now(),
                'finance_logistique_validee_par' => $user->id,
            ]);

            $libelleAction = $apresModificationDuree
                ? 'Validation Finance — Logistique (après modification de durée)'
                : 'Validation Finance — Logistique';

            $messageLog = $apresModificationDuree
                ? 'Dépenses logistiques revalidées par Finance après prolongement ou raccourcissement de la mission.'
                : 'Dépenses logistiques validées par Finance.';

            if ($commentaire !== '') {
                $messageLog .= ' ' . $commentaire;
            }

            MissionLog::create([
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'action' => 'approbation',
                'etape_concernee' => $libelleAction,
                'commentaire' => $messageLog,
            ]);

            DB::commit();

            return redirect()
                ->route('missions.validation-finance')
                ->with('success', $apresModificationDuree
                    ? 'Logistique retraitée. Le récapitulatif Finance a été mis à jour avec les nouveaux montants.'
                    : 'Logistique traitée. La mission est intégrée au récapitulatif des dépenses.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Échec validation Finance logistique', [
                'mission_id' => $mission->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Erreur lors de la validation Finance.');
        }
    }

    private function peutVoirRecapLogistique(User $user): bool
    {
        return $user->peutVoirRecapLogistique();
    }

    private function totalPerDiemMission(Mission $mission): float
    {
        if (! $mission->relationLoaded('missionParticipants')) {
            $mission->load(['missionParticipants' => fn ($q) => $q->where('role_dans_mission', 'missionnaire')]);
        }

        return (float) $mission->missionParticipants
            ->where('role_dans_mission', 'missionnaire')
            ->sum(fn ($participant) => (float) ($participant->per_diem ?? 0));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Mission>
     */
    private function queryMissionsTraiteesPourUtilisateur(User $user)
    {
        $idsHistoriqueValidees = $this->idsMissionsHistoriqueValideesParUtilisateur($user);

        $query = Mission::query();
        $this->appliquerFiltreMissionsVisiblesUtilisateur($query, $user);

        return $query->where(function ($q) use ($user, $idsHistoriqueValidees) {
            $q->where('current_step', Mission::STEP_CLOTUREE)
                ->orWhere('status', 'rejete');

            if ($idsHistoriqueValidees !== []) {
                $q->orWhereIn('id', $idsHistoriqueValidees);
            }

            if ($user->isFinance()) {
                $q->orWhere('finance_logistique_validee_par', $user->id);
            }
        });
    }

    /**
     * @return array{
     *     global: array<string, mixed>,
     *     periodes: array<int, array<string, mixed>>,
     *     sites_populaires: array<int, array{site: string, count: int}>
     * }
     */
    private function construireRecapMissionsTraitees(User $user, string $periode): array
    {
        $periode = in_array($periode, ['semaine', 'mois', 'annee'], true) ? $periode : 'mois';

        $missions = $this->queryMissionsTraiteesPourUtilisateur($user)
            ->with(['missionParticipants' => fn ($q) => $q->where('role_dans_mission', 'missionnaire')])
            ->orderBy('date_debut')
            ->get();

        $buckets = [];
        $compteurSitesGlobal = [];
        $totalVisitesSites = 0;
        $totalSitesDansMissions = 0;

        foreach ($missions as $mission) {
            $sites = $this->sitesPourRecapMission($mission);
            $nbSitesMission = count($sites);
            $totalSitesDansMissions += $nbSitesMission;

            foreach ($sites as $site) {
                $compteurSitesGlobal[$site] = ($compteurSitesGlobal[$site] ?? 0) + 1;
                $totalVisitesSites++;
            }

            $montant = $this->totalPerDiemMission($mission);
            $date = Carbon::parse($mission->date_debut)->locale('fr');
            $clePeriode = match ($periode) {
                'semaine' => $date->isoFormat('GGGG-[S]WW'),
                'annee' => $date->format('Y'),
                default => $date->format('Y-m'),
            };

            if (! isset($buckets[$clePeriode])) {
                $buckets[$clePeriode] = [
                    'cle' => $clePeriode,
                    'libelle' => match ($periode) {
                        'semaine' => 'Semaine '.$date->isoFormat('WW').' — '.$date->isoFormat('YYYY'),
                        'annee' => $date->isoFormat('YYYY'),
                        default => ucfirst($date->isoFormat('MMMM YYYY')),
                    },
                    'nb_missions' => 0,
                    'montant_total' => 0.0,
                    'sites_visites' => 0,
                    'compteur_sites' => [],
                ];
            }

            $buckets[$clePeriode]['nb_missions']++;
            $buckets[$clePeriode]['montant_total'] += $montant;
            $buckets[$clePeriode]['sites_visites'] += $nbSitesMission;

            foreach ($sites as $site) {
                $buckets[$clePeriode]['compteur_sites'][$site] = ($buckets[$clePeriode]['compteur_sites'][$site] ?? 0) + 1;
            }
        }

        krsort($buckets);

        $periodes = array_map(function (array $bucket) {
            $nb = max(1, $bucket['nb_missions']);
            $sitesUniques = count($bucket['compteur_sites']);
            $visites = array_sum($bucket['compteur_sites']);

            return [
                'cle' => $bucket['cle'],
                'libelle' => $bucket['libelle'],
                'nb_missions' => $bucket['nb_missions'],
                'montant_total' => round($bucket['montant_total'], 2),
                'montant_moyen_par_mission' => round($bucket['montant_total'] / $nb, 2),
                'sites_visites_total' => $bucket['sites_visites'],
                'sites_moyen_par_mission' => round($bucket['sites_visites'] / $nb, 2),
                'visites_par_site_moyenne' => $sitesUniques > 0 ? round($visites / $sitesUniques, 2) : 0.0,
            ];
        }, array_values($buckets));

        $globalNb = $missions->count();
        $globalMontant = (float) $missions->sum(fn (Mission $m) => $this->totalPerDiemMission($m));
        $nbPeriodes = max(1, count($buckets));
        $sitesUniquesGlobal = count($compteurSitesGlobal);

        arsort($compteurSitesGlobal);
        $sitesPopulaires = collect($compteurSitesGlobal)
            ->take(10)
            ->map(fn (int $count, string $site) => ['site' => $site, 'count' => $count])
            ->values()
            ->all();

        return [
            'global' => [
                'nb_missions' => $globalNb,
                'moyenne_missions_par_periode' => round($globalNb / $nbPeriodes, 2),
                'montant_total' => round($globalMontant, 2),
                'montant_moyen_par_mission' => $globalNb > 0 ? round($globalMontant / $globalNb, 2) : 0.0,
                'sites_visites_total' => $totalSitesDansMissions,
                'sites_moyen_par_mission' => $globalNb > 0 ? round($totalSitesDansMissions / $globalNb, 2) : 0.0,
                'visites_par_site_moyenne' => $sitesUniquesGlobal > 0
                    ? round($totalVisitesSites / $sitesUniquesGlobal, 2)
                    : 0.0,
                'nb_sites_uniques' => $sitesUniquesGlobal,
            ],
            'periodes' => $periodes,
            'sites_populaires' => $sitesPopulaires,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sitesPourRecapMission(Mission $mission): array
    {
        $sites = $mission->sites_mission ?? [];

        if (is_array($sites) && $sites !== []) {
            $labels = [];
            foreach ($sites as $site) {
                $label = is_string($site) ? $site : (string) ($site['label'] ?? '');
                if ($label !== '') {
                    $labels[] = $label;
                }
            }

            if ($labels !== []) {
                return array_values(array_unique($labels));
            }
        }

        return MissionSites::extraireDepuisPerimetre($mission->perimetre);
    }

    /**
     * @return array{
     *     global: array<string, mixed>,
     *     periodes: array<int, array<string, mixed>>,
     *     plage: array{debut: string, fin: string, libelle: string}
     * }
     */
    private function construireRecapLogistique(string $context = 'finance', ?Carbon $dateDebut = null, ?Carbon $dateFin = null): array
    {
        $context = in_array($context, ['facilities', 'finance'], true) ? $context : 'finance';
        $dateDebut = ($dateDebut ?? now()->startOfMonth())->copy()->startOfDay();
        $dateFin = ($dateFin ?? now())->copy()->endOfDay();

        $categoriesMeta = [
            'per_diem' => 'Per diems',
            'prix_carburant' => 'Essence / carburant',
            'prix_transport' => 'Transport',
            'prix_logement' => 'Logement',
            'autres_frais' => 'Autres frais',
        ];

        $query = Mission::query()
            ->whereNotNull('total_logistique')
            ->where('total_logistique', '>', 0)
            ->whereDate('date_debut', '>=', $dateDebut->toDateString())
            ->whereDate('date_debut', '<=', $dateFin->toDateString())
            ->with(['missionParticipants' => fn ($q) => $q->where('role_dans_mission', 'missionnaire')])
            ->orderBy('date_debut');

        if ($context === 'finance') {
            $query->whereNotNull('finance_logistique_validee_at');
        } else {
            $query->whereNotIn('current_step', $this->etapesAvantValidationFinanceLogistique());
        }

        $missions = $query->get();

        $totauxPeriode = array_fill_keys(array_keys($categoriesMeta), 0.0);
        $totalPeriode = 0.0;

        foreach ($missions as $mission) {
            $totaux = array_fill_keys(array_keys($categoriesMeta), 0.0);

            foreach ($mission->missionParticipants as $participant) {
                foreach (array_keys($categoriesMeta) as $cle) {
                    $totaux[$cle] += (float) ($participant->{$cle} ?? 0);
                }
            }

            $totalMission = (float) $mission->total_logistique;
            $totalPeriode += $totalMission;

            foreach ($totaux as $cle => $montant) {
                $totauxPeriode[$cle] += $montant;
            }
        }

        $globalNb = $missions->count();
        $libellePlage = 'Du '.$dateDebut->locale('fr')->isoFormat('D MMMM YYYY')
            .' au '.$dateFin->locale('fr')->isoFormat('D MMMM YYYY');

        $periodes = $globalNb > 0 ? [[
            'cle' => $dateDebut->toDateString().'_'.$dateFin->toDateString(),
            'libelle' => $libellePlage,
            'nb_missions' => $globalNb,
            'total' => round($totalPeriode, 2),
            'moyenne_par_mission' => round($totalPeriode / max(1, $globalNb), 2),
            'categories' => $this->formaterCategoriesRecap($totauxPeriode, max(1, $globalNb), $categoriesMeta),
        ]] : [];

        $globalTotal = (float) $missions->sum(fn (Mission $m) => (float) $m->total_logistique);
        $globalCategories = array_fill_keys(array_keys($categoriesMeta), 0.0);

        foreach ($missions as $mission) {
            foreach ($mission->missionParticipants as $participant) {
                foreach (array_keys($categoriesMeta) as $cle) {
                    $globalCategories[$cle] += (float) ($participant->{$cle} ?? 0);
                }
            }
        }

        return [
            'global' => [
                'nb_missions' => $globalNb,
                'total' => round($globalTotal, 2),
                'moyenne_par_mission' => $globalNb > 0 ? round($globalTotal / $globalNb, 2) : 0.0,
                'categories' => $this->formaterCategoriesRecap($globalCategories, max(1, $globalNb), $categoriesMeta),
            ],
            'periodes' => $periodes,
            'plage' => [
                'debut' => $dateDebut->toDateString(),
                'fin' => $dateFin->toDateString(),
                'libelle' => $libellePlage,
            ],
        ];
    }

    /**
     * @param  array<string, float>  $totaux
     * @param  array<string, string>  $labels
     * @return array<int, array{cle: string, libelle: string, total: float, moyenne: float}>
     */
    private function formaterCategoriesRecap(array $totaux, int $nbMissions, array $labels): array
    {
        $result = [];

        foreach ($labels as $cle => $libelle) {
            $total = round((float) ($totaux[$cle] ?? 0), 2);
            $result[] = [
                'cle' => $cle,
                'libelle' => $libelle,
                'total' => $total,
                'moyenne' => round($total / max(1, $nbMissions), 2),
            ];
        }

        return $result;
    }

    public function traitementFacilities(Request $request, Mission $mission): Response
    {
        if (! $request->user()->isLogistique()) {
            abort(403, 'Accès réservé aux profils logistique.');
        }

        $mission->load(['demandeur', 'participants']);
        $missionnaires = $this->missionnairesMission($mission);

        $missionData = $mission->toArray();
        $missionData['participants'] = $this->formaterMissionnairesPourAffichage(
            $missionnaires,
            $request->user(),
        );

        return Inertia::render('Missions/ValidationFacilities', [
            'mission' => $missionData,
            'chauffeurs' => $this->listeChauffeurs(),
            'logistique_initiale' => $this->reconstituerFormulaireFacilities($missionnaires, $mission),
        ]);
    }

    public function apercuFicheValidation(Request $request, Mission $mission): HttpResponse
    {
        $user = $request->user();

        if (! $this->peutImprimerFicheValidation($user, $mission)) {
            abort(403, 'La fiche de validation n\'est disponible qu\'après signature du Directeur Général.');
        }

        $mission->load(['demandeur', 'participants', 'logs.auteur']);
        $pdf = $this->construirePdfFicheValidation($mission);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="fiche_validation_mission_' . $mission->id . '.pdf"',
        ]);
    }

    public function apercuOrdreMission(Request $request, Mission $mission): HttpResponse
    {
        $user = $request->user();

        if (! $this->peutValiderRh($user)) {
            abort(403, 'Accès réservé au personnel des Ressources Humaines.');
        }

        if (! $this->estEtapeRh($mission) || ! $this->peutTraiterEtapeCourante($user, $mission)) {
            abort(403, 'L\'aperçu de l\'ordre de mission n\'est disponible qu\'à l\'étape de validation RH post-logistique.');
        }

        $pdf = $this->construirePdfOrdreMission($mission);

        return $this->reponsePdfInline($pdf['content'], 'apercu_ordre_mission_' . $mission->id . '.pdf');
    }

    public function vueRapportsMission(Request $request): Response
    {
        $user = $request->user();

        $query = Mission::with(['demandeur', 'beneficiaire', 'rapportSignataire'])
            ->whereIn('current_step', [
                Mission::STEP_ATTENTE_RAPPORT,
                Mission::STEP_ATTENTE_VALIDATION_RAPPORT,
                Mission::STEP_CLOTUREE,
            ])
            ->orderByDesc('updated_at');

        if (! $this->utilisateurAVisibiliteMissionsComplete($user)) {
            $this->appliquerFiltreMissionsVisiblesUtilisateur($query, $user, false);
        }

        $this->appliquerFiltreNumeroMission($query, $request);

        $missions = $query->paginate(20)->withQueryString();

        return Inertia::render('Missions/RapportsMissionList', [
            'missions' => $missions,
            'filtreNumero' => $this->filtreNumeroMissionPourVue($request),
        ]);
    }

    private const NB_MAX_PIECES_JOINTES_RAPPORT = 10;

    private const TAILLE_MAX_PIECE_JOINTE_RAPPORT_KO = 10240;

    private const TAILLE_MAX_TOTAL_PIECES_JOINTES_RAPPORT = 50 * 1024 * 1024;

    /**
     * @return array<int, string>
     */
    private function extensionsPiecesJointesRapport(): array
    {
        return [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf',
            'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp',
            'mp4', 'mov', 'avi', 'mkv', 'webm',
            'zip',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function reglesPiecesJointesRapport(): array
    {
        return [
            'nullable',
            'array',
            'max:' . self::NB_MAX_PIECES_JOINTES_RAPPORT,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function regleFichierPieceJointeRapport(): array
    {
        return [
            'file',
            'max:' . self::TAILLE_MAX_PIECE_JOINTE_RAPPORT_KO,
            'mimes:' . implode(',', $this->extensionsPiecesJointesRapport()),
        ];
    }

    private function peutConsulterRapportMission(User $user, Mission $mission): bool
    {
        return $user->isAdmin()
            || $user->isAudit()
            || $this->estDemandeur($user, $mission)
            || $this->estParticipant($user, $mission);
    }

    private function nomFichierPieceJointeSecurise(string $nomOriginal): string
    {
        $extension = strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION));
        $base = pathinfo($nomOriginal, PATHINFO_FILENAME);
        $nom = Str::slug(mb_substr($base, 0, 80));

        if ($nom === '') {
            $nom = 'fichier';
        }

        return $extension !== '' ? "{$nom}.{$extension}" : $nom;
    }

    /**
     * @param  array<int, UploadedFile>  $fichiers
     * @return array<int, MissionRapportPieceJointe>
     */
    private function enregistrerPiecesJointesRapport(Mission $mission, User $user, array $fichiers): array
    {
        $pieces = [];
        $tailleTotale = 0;

        foreach ($fichiers as $fichier) {
            $tailleTotale += $fichier->getSize();
        }

        if ($tailleTotale > self::TAILLE_MAX_TOTAL_PIECES_JOINTES_RAPPORT) {
            throw ValidationException::withMessages([
                'pieces_jointes' => 'La taille totale des pièces jointes ne doit pas dépasser 50 Mo.',
            ]);
        }

        foreach ($fichiers as $fichier) {
            $nomOriginal = $fichier->getClientOriginalName();
            $chemin = $fichier->storeAs(
                "missions/rapports/{$mission->id}",
                Str::uuid() . '_' . $this->nomFichierPieceJointeSecurise($nomOriginal),
                'local',
            );

            $pieces[] = MissionRapportPieceJointe::create([
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'nom_fichier' => $nomOriginal,
                'chemin' => $chemin,
                'mime_type' => $fichier->getMimeType() ?? 'application/octet-stream',
                'taille' => $fichier->getSize(),
            ]);
        }

        return $pieces;
    }

    /**
     * @param  array<int, MissionRapportPieceJointe>  $pieces
     */
    private function supprimerPiecesJointesRapport(array $pieces): void
    {
        foreach ($pieces as $piece) {
            Storage::disk('local')->delete($piece->chemin);
            $piece->delete();
        }
    }

    public function soumettreRapportMission(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        if ($mission->current_step !== Mission::STEP_ATTENTE_RAPPORT || ! $this->estMissionnaire($user, $mission)) {
            abort(403, 'Vous n\'êtes pas autorisé à soumettre le rapport de cette mission.');
        }

        if ($mission->rapport_soumis_at !== null) {
            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_RAPPORT,
                'error',
                'Le rapport a déjà été soumis.',
                $user,
            );
        }

        $validated = $request->validate(array_merge(
            MissionRapport::reglesValidation(),
            [
                'signataire_nom' => ['required', 'string', 'max:255'],
                'signature' => ['required', 'string', 'max:500000', 'regex:/^data:image\/(png|jpe?g|webp);base64,/'],
                'pieces_jointes' => $this->reglesPiecesJointesRapport(),
                'pieces_jointes.*' => $this->regleFichierPieceJointeRapport(),
            ],
        ));

        $reponses = collect($validated['reponses'] ?? [])
            ->map(fn ($v) => trim((string) $v))
            ->all();
        $contenuCompile = MissionRapport::compilerContenu($reponses);

        $fichiers = $request->file('pieces_jointes', []);
        if (! is_array($fichiers)) {
            $fichiers = [];
        }

        DB::beginTransaction();
        $piecesEnregistrees = [];
        try {
            $signataireNom = trim($validated['signataire_nom']);
            $signature = $this->optimiserSignatureImage($validated['signature']);

            $mission->update([
                'rapport_reponses' => $reponses,
                'rapport_contenu' => $contenuCompile,
                'rapport_signature_image' => $signature,
                'rapport_signataire_nom' => $signataireNom,
                'rapport_signataire_id' => $user->id,
                'rapport_soumis_at' => now(),
                'current_step' => Mission::STEP_ATTENTE_VALIDATION_RAPPORT,
            ]);

            if ($fichiers !== []) {
                $piecesEnregistrees = $this->enregistrerPiecesJointesRapport($mission, $user, $fichiers);
            }

            $pdfPath = $this->genererPdfRapportMission($mission);
            $mission->update(['rapport_pdf_path' => $pdfPath]);

            $this->enregistrerLogValidation(
                $mission,
                $user,
                'Rapport de mission — Soumission',
                $validated['signature'],
                "Rapport signé par {$signataireNom}.",
            );

            MissionNotificationService::notifyRapportSoumis($mission);
            MissionNotificationService::notifyDemandeurChangementEtape(
                $mission,
                $this->libelleEtapeMission($mission),
            );
            $mission->update(['last_reminder_at' => null]);

            DB::commit();

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_RAPPORT,
                'success',
                'Rapport de mission soumis. Le demandeur a été notifié pour validation.',
                $user,
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($piecesEnregistrees !== []) {
                $this->supprimerPiecesJointesRapport($piecesEnregistrees);
            }
            Log::error('Échec soumission rapport mission', ['mission_id' => $mission->id, 'error' => $e->getMessage()]);

            if ($e instanceof ValidationException) {
                throw $e;
            }

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_RAPPORT,
                'error',
                'Erreur lors de la soumission du rapport.',
                $user,
            );
        }
    }

    public function validerRapportMission(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        if ($mission->current_step !== Mission::STEP_ATTENTE_VALIDATION_RAPPORT || ! $this->estDemandeur($user, $mission)) {
            abort(403, 'Seul le demandeur peut valider le rapport de mission.');
        }

        $validated = $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $mission->update([
                'current_step' => Mission::STEP_CLOTUREE,
                'status' => 'cloture',
                'rapport_valide_at' => now(),
            ]);

            MissionLog::create([
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'action' => 'validation',
                'etape_concernee' => 'Clôture — Validation rapport',
                'commentaire' => $validated['commentaire'] ?? 'Rapport de mission validé. Mission clôturée officiellement.',
            ]);

            MissionNotificationService::notifyClotureMission($mission);
            $this->notifierEtapeCourante($mission, 'La mission a été clôturée officiellement après validation du rapport.');

            DB::commit();

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_VALIDATION_RAPPORT,
                'success',
                'Rapport validé. La mission est officiellement clôturée.',
                $user,
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->redirectApresActionEtape(
                Mission::STEP_ATTENTE_VALIDATION_RAPPORT,
                'error',
                'Erreur lors de la validation du rapport.',
                $user,
            );
        }
    }

    public function modifierDureeMission(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        if (! $this->peutModifierDureeMission($user, $mission)) {
            abort(403, 'Vous ne pouvez plus modifier la durée de cette mission.');
        }

        $missionnaires = $this->missionnairesMission($mission);
        $participantIds = $missionnaires->pluck('id')->map(fn ($id) => (int) $id)->all();

        $validated = $request->validate([
            'date_fin' => ['required', 'date', 'after:'.$mission->date_fin->format('Y-m-d')],
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
            'missionnaire_ids' => ['required', 'array', 'min:1'],
            'missionnaire_ids.*' => ['integer', Rule::in($participantIds)],
            'sites_prolongation' => ['required', 'array', 'min:1'],
            'sites_prolongation.*' => ['string', Rule::in(MissionSites::allLabels())],
            'descriptions_sites_prolongation' => ['nullable', 'array'],
        ]);

        $descriptionsProlongation = $this->validerDescriptionsSites(
            $validated['sites_prolongation'],
            $validated['descriptions_sites_prolongation'] ?? [],
        );

        $idsSelectionnes = collect($validated['missionnaire_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        if ($idsSelectionnes->count() !== count($validated['missionnaire_ids'])) {
            throw ValidationException::withMessages([
                'missionnaire_ids' => 'Chaque missionnaire ne peut être sélectionné qu’une seule fois.',
            ]);
        }

        DB::beginTransaction();
        try {
            $ancienDebut = $mission->date_debut?->format('d/m/Y');
            $ancienFin = $mission->date_fin?->format('d/m/Y');
            $nouveauDebut = $mission->date_debut?->format('d/m/Y');
            $nouveauFin = Carbon::parse($validated['date_fin'])->format('d/m/Y');

            $selection = $this->appliquerSelectionMissionnairesPourModificationDuree(
                $mission,
                $idsSelectionnes->all(),
            );
            $sitesProlongation = MissionSites::validerSelection($validated['sites_prolongation']);
            $sitesMission = $mission->sites_mission ?? MissionSites::extraireDepuisPerimetre($mission->perimetre);
            $tousSites = array_values(array_unique(array_merge($sitesMission, $sitesProlongation)));

            $etapeReprise = $mission->current_step;

            $payloadUpdate = [
                'date_debut' => $mission->date_debut,
                'date_fin' => $validated['date_fin'],
                'perimetre' => MissionSites::perimetreDepuisSites($tousSites),
                'sites_prolongation' => $sitesProlongation,
                'descriptions_sites_prolongation' => $descriptionsProlongation,
                'current_step' => Mission::STEP_ATTENTE_FACILITIES,
                'status' => 'en_cours',
                'duree_modifiee_at' => now(),
                'finance_logistique_validee_at' => null,
                'finance_logistique_validee_par' => null,
                'etape_reprise_apres_prolongation' => $etapeReprise,
                'ordre_prolongation_pdf_path' => null,
                'ordre_prolongation_signe_at' => null,
                'prolongation_donnees' => [
                    'type' => 'prolongation',
                    'ancien_debut' => $ancienDebut,
                    'ancien_fin' => $ancienFin,
                    'nouveau_debut' => $nouveauDebut,
                    'nouveau_fin' => $nouveauFin,
                    'motif' => $validated['motif'],
                    'missionnaires' => $selection['conserves'],
                    'sites_prolongation' => $sitesProlongation,
                    'descriptions_sites_prolongation' => $descriptionsProlongation,
                ],
            ];

            $mission->update($payloadUpdate);

            $detailsMissionnaires = 'Missionnaires conservés : '.implode(', ', $selection['conserves']).'.';
            if ($selection['retires'] !== []) {
                $detailsMissionnaires .= ' Retirés de la mission : '.implode(', ', $selection['retires']).'.';
            }
            $detailsSites = 'Sites prolongation : '.implode(', ', $sitesProlongation).'.';

            MissionLog::create([
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'action' => 'modification',
                'etape_concernee' => 'Modification durée — Retour Facilities',
                'commentaire' => "Durée modifiée ({$ancienDebut} → {$ancienFin} remplacé par {$nouveauDebut} → {$nouveauFin}). "
                    . $detailsSites
                    . ' '
                    . $detailsMissionnaires
                    . " Motif : {$validated['motif']}",
            ]);

            $this->notifierEtapeCourante(
                $mission,
                'La durée de la mission a été modifiée. Une nouvelle saisie logistique Facilities est requise, puis une revalidation Finance.',
            );

            DB::commit();

            return redirect()
                ->route('missions.index')
                ->with('success', 'Prolongation enregistrée. Les données logistiques initiales sont conservées. Complétez la saisie Facilities pour la période prolongée, puis la revalidation Finance.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->route('missions.index')
                ->with('error', 'Erreur lors de la modification de la durée.');
        }
    }

    public function apercuRapportMission(Request $request, Mission $mission): HttpResponse
    {
        $user = $request->user();

        if ($mission->rapport_pdf_path === null && $mission->rapport_contenu === null) {
            abort(404, 'Aucun rapport disponible.');
        }

        if (! $this->peutConsulterRapportMission($user, $mission)) {
            abort(403);
        }

        $pdf = $this->construirePdfRapportMission($mission);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="rapport_mission_' . $mission->id . '.pdf"',
        ]);
    }

    public function telechargerRapportPdf(Request $request, Mission $mission): HttpResponse
    {
        return $this->apercuRapportMission($request, $mission);
    }

    public function telechargerPieceJointeRapport(
        Request $request,
        Mission $mission,
        MissionRapportPieceJointe $pieceJointe,
    ): StreamedResponse {
        $user = $request->user();

        if ($pieceJointe->mission_id !== $mission->id) {
            abort(404);
        }

        if (! $this->peutConsulterRapportMission($user, $mission)) {
            abort(403);
        }

        if (! Storage::disk('local')->exists($pieceJointe->chemin)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('local')->download(
            $pieceJointe->chemin,
            $pieceJointe->nom_fichier,
        );
    }

    /**
     * @return array{content: string, hash: string}
     */
    private function construirePdfRapportMission(Mission $mission): array
    {
        $html = view('missions.rapport-mission', [
            'mission' => $mission,
            'signataireNom' => $mission->rapport_signataire_nom ?? '—',
            'logoDataUri' => $this->logoDataUriOrdreMission(),
            'sectionsRapport' => MissionRapport::sectionsAffichables($mission->rapport_reponses),
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $content = $dompdf->output();

        return [
            'content' => $content,
            'hash' => hash('sha256', $content),
        ];
    }

    private function genererPdfRapportMission(Mission $mission): string
    {
        $pdf = $this->construirePdfRapportMission($mission);
        $filename = "missions/rapport_mission_{$mission->id}_" . now()->format('Y-m-d_His') . '.pdf';
        Storage::disk('local')->put($filename, $pdf['content']);

        return $filename;
    }

    public function apercuOrdreProlongation(Request $request, Mission $mission): HttpResponse
    {
        $user = $request->user();

        if (! $this->peutVoirPdfOrdreProlongation($user, $mission, $this->estParticipant($user, $mission))
            && ! ($this->peutValiderRh($user) && $this->estEtapeRh($mission))
            && ! ($user->isResponsableRh() && $mission->current_step === Mission::STEP_ATTENTE_SIGNATURE_RRH)) {
            abort(403);
        }

        if (! $this->estProlongationEnAttenteSignature($mission) && $mission->ordre_prolongation_signe_at === null) {
            abort(404, 'Aucun ordre de prolongation disponible.');
        }

        if ($mission->ordre_prolongation_pdf_path && Storage::disk('local')->exists($mission->ordre_prolongation_pdf_path)) {
            Storage::disk('local')->delete($mission->ordre_prolongation_pdf_path);
        }

        $signature = null;
        $nomSignataire = null;
        if ($mission->ordre_prolongation_signe_at) {
            $logSignature = $mission->logs()
                ->where('etape_concernee', 'like', '%prolongation%')
                ->whereNotNull('signature_image')
                ->latest()
                ->first();
            $signature = $logSignature?->signature_image;
            $nomSignataire = $logSignature ? $this->nomSignatairePourUser($logSignature->auteur) : null;
        }

        $pdf = $this->construirePdfOrdreProlongation($mission, $signature, $nomSignataire);

        return $this->reponsePdfInline($pdf['content'], 'apercu_ordre_prolongation_' . $mission->id . '.pdf');
    }

    public function telechargerPdf(Request $request, Mission $mission): HttpResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $this->peutTelechargerPdfOrdre($user, $mission, $this->estParticipant($user, $mission))) {
            abort(403, 'Vous n’êtes pas autorisé à consulter cet ordre de mission.');
        }

        if (! $mission->pdf_path) {
            return redirect()->back()->with('error', 'Aucun ordre de mission n’a encore été généré.');
        }

        try {
            $content = $this->regenererPdfOrdreMissionStocke($mission);
        } catch (\RuntimeException) {
            return redirect()->back()->with('error', 'Impossible de générer l’ordre de mission.');
        }

        return $this->reponsePdfInline(
            $content,
            "ordre_mission_{$mission->id}.pdf",
        );
    }

    public function edit(Request $request, Mission $mission): Response
    {
        $user = $request->user();

        $peutModifier = $this->demandeurPeutModifierDemande($user, $mission)
            || $this->n1PeutModifierDemande($user, $mission);

        if (! $peutModifier) {
            abort(403, 'Vous n’êtes pas autorisé à modifier cette mission.');
        }

        $mission->load('participants');
        $demandeurEffectif = $this->demandeurEffectifPourMission($user, $mission);

        $missionData = $mission->toArray();
        $missionData['participant_profil_ids'] = $this->profilIdsMissionnaires($mission);
        $missionData['sites_mission'] = $this->sitesMissionPourFormulaire($mission);

        return Inertia::render('Missions/Edit', [
            'mission' => $missionData,
            'collaborateurs' => $this->listeCollaborateursMissionPourEdition($demandeurEffectif, $mission),
            'selectionMissionnairesIllimitee' => $this->demandeurPeutSelectionnerTousMissionnaires($demandeurEffectif),
            'estModificationN1' => $this->n1PeutModifierDemande($user, $mission),
            'sitesCatalog' => MissionSites::catalog(),
        ]);
    }

    public function update(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        $peutModifier = $this->demandeurPeutModifierDemande($user, $mission)
            || $this->n1PeutModifierDemande($user, $mission);

        if (! $peutModifier) {
            abort(403);
        }

        $action = $request->input('action', 'soumettre');
        $estBrouillon = $action === 'brouillon' && $this->demandeurPeutModifierDemande($user, $mission);
        $estModificationN1 = $this->n1PeutModifierDemande($user, $mission);
        $demandeurEffectif = $this->demandeurEffectifPourMission($user, $mission);

        $validated = $request->validate($this->reglesMission(! $estBrouillon));

        $profilIds = $validated['participant_profil_ids'];
        $this->asserterMissionnairesAutorises($demandeurEffectif, $profilIds);

        $nextStep = $estBrouillon ? Mission::STEP_BROUILLON : Mission::STEP_ATTENTE_N1;
        $nextStatus = $estBrouillon ? 'brouillon' : 'en_cours';

        $beneficiaireId = $this->premierUserIdDepuisProfils($profilIds);
        $sitesPayload = $this->preparerSitesMissionPayload($validated['sites_mission']);
        $descriptionsSites = $this->validerDescriptionsSites(
            $validated['sites_mission'],
            $validated['descriptions_sites'] ?? [],
        );

        $payload = array_merge(collect($validated)->except(['participant_profil_ids', 'sites_mission', 'descriptions_sites'])->toArray(), [
            'beneficiaire_id' => $beneficiaireId ?? $mission->demandeur_id,
            'status' => $nextStatus,
            'current_step' => $nextStep,
            'perimetre' => $sitesPayload['perimetre'],
            'sites_mission' => $sitesPayload['sites_mission'],
            'descriptions_sites' => $descriptionsSites,
        ]);

        if ($estModificationN1 && ! $estBrouillon) {
            $payload['n2_beneficiaire_id'] = $user->id;
        }

        $mission->update($payload);

        $this->syncMissionnairesFromProfilIds($mission, $profilIds);

        if (! $estBrouillon) {
            $this->attribuerNumeroMissionSiNecessaire($mission);
        }

        MissionLog::create([
            'mission_id' => $mission->id,
            'user_id' => $user->id,
            'action' => 'modification',
            'etape_concernee' => $estModificationN1 ? 'Correction N+1' : 'Correction',
            'commentaire' => $estModificationN1
                ? 'Demande corrigée par le N+1. En attente de sa validation.'
                : 'Mission mise à jour.',
        ]);

        if (! $estBrouillon) {
            $mission->load('participants');
            $this->notifierParticipants($mission);
        }

        if ($estModificationN1 && ! $estBrouillon) {
            return redirect()
                ->route('missions.show', $mission)
                ->with('success', 'Demande mise à jour. Vous pouvez maintenant la valider.');
        }

        return redirect()->route('missions.index')->with('success', 'La mission a été mise à jour.');
    }

    public function destroy(Request $request, Mission $mission): RedirectResponse
    {
        $user = $request->user();

        if (! $this->estDemandeur($user, $mission)) {
            abort(403);
        }

        if ($mission->status === 'valide') {
            abort(403, 'Une mission validée ne peut plus être supprimée.');
        }

        DB::beginTransaction();
        try {
            if ($mission->pdf_path) {
                Storage::disk('local')->delete($mission->pdf_path);
            }
            if ($mission->ordre_prolongation_pdf_path) {
                Storage::disk('local')->delete($mission->ordre_prolongation_pdf_path);
            }
            $mission->logs()->delete();
            $mission->missionParticipants()->delete();
            $mission->participants()->detach();
            $mission->delete();

            DB::commit();

            return redirect()->route('missions.index')->with('success', 'La demande a été supprimée.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erreur lors de la suppression.');
        }
    }
}
