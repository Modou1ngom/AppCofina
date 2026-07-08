<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Models\Mission;
use App\Models\MissionParticipant;
use App\Models\User;
use App\Notifications\MissionOrdreGenereNotification;
use Illuminate\Support\Facades\Log;

class MissionNotificationService
{
    private static function libelleNumeroMission(Mission $mission): string
    {
        return $mission->numero_mission !== null ? (string) $mission->numero_mission : '—';
    }

    public static function notifyEtape(Mission $mission, User $destinataire, string $etapeLabel, string $actionUrl): void
    {
        if (empty($destinataire->email)) {
            return;
        }

        $numero = self::libelleNumeroMission($mission);

        $data = [
            'mission_id' => $mission->id,
            'numero_mission' => $numero,
            'objet' => $mission->objet,
            'etape' => $etapeLabel,
            'action_url' => $actionUrl,
            'demandeur' => $mission->demandeur?->name ?? '—',
        ];

        SendEmail::dispatch(
            "Mission N° {$numero} — {$etapeLabel}",
            $destinataire->email,
            [],
            [],
            'emails.missions.etape',
            $data,
        );

        $destinataire->notify(new MissionOrdreGenereNotification(
            $mission->id,
            $mission->objet,
            "Une mission nécessite votre intervention : {$etapeLabel}",
            $actionUrl,
        ));
    }

    public static function notifyRappelEtape(Mission $mission, User $destinataire, string $etapeLabel, string $actionUrl): void
    {
        if (empty($destinataire->email)) {
            return;
        }

        $numero = self::libelleNumeroMission($mission);

        $data = [
            'mission_id' => $mission->id,
            'numero_mission' => $numero,
            'objet' => $mission->objet,
            'etape' => $etapeLabel,
            'action_url' => $actionUrl,
            'demandeur' => $mission->demandeur?->name ?? '—',
        ];

        SendEmail::dispatch(
            "[Rappel] Mission N° {$numero} — {$etapeLabel}",
            $destinataire->email,
            [],
            [],
            'emails.missions.rappel-etape',
            $data,
        );
    }

    public static function notifyDemandeurChangementEtape(Mission $mission, string $etapeLabel): void
    {
        $mission->loadMissing('demandeur');
        $demandeur = $mission->demandeur;

        if ($demandeur === null || empty($demandeur->email)) {
            return;
        }

        $numero = self::libelleNumeroMission($mission);

        $data = [
            'mission_id' => $mission->id,
            'numero_mission' => $numero,
            'objet' => $mission->objet,
            'etape' => $etapeLabel,
            'action_url' => route('missions.show', $mission),
            'message' => 'Votre demande de mission a évolué dans le circuit de validation.',
        ];

        SendEmail::dispatch(
            "Mission N° {$numero} — {$etapeLabel}",
            $demandeur->email,
            [],
            [],
            'emails.missions.demandeur-etape',
            $data,
        );

        $demandeur->notify(new MissionOrdreGenereNotification(
            $mission->id,
            $mission->objet,
            "Votre demande « {$mission->objet} » — {$etapeLabel}",
            route('missions.show', $mission),
        ));
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function responsablesPourEtape(Mission $mission): \Illuminate\Support\Collection
    {
        $mission->loadMissing(['demandeur', 'participants']);

        $responsables = match ($mission->current_step) {
            Mission::STEP_ATTENTE_N1 => collect(array_filter([
                $mission->n2_beneficiaire_id ? User::find($mission->n2_beneficiaire_id) : null,
            ]))->merge(self::utilisateursParRole('dga')),
            Mission::STEP_ATTENTE_DGA => self::utilisateursParRole('dga'),
            Mission::STEP_ATTENTE_MD => self::utilisateursParRole('md'),
            Mission::STEP_ATTENTE_FACILITIES => self::utilisateursParRole('logistique')->merge(self::utilisateursParRole('facilities')),
            Mission::STEP_ATTENTE_RH, 'ATTENTE_RH_LOGISTIQUE' => self::utilisateursParRole('rh'),
            Mission::STEP_ATTENTE_SIGNATURE_RRH => self::utilisateursParRole('responsable_rh'),
            Mission::STEP_ATTENTE_RAPPORT => self::missionnairesUsers($mission),
            Mission::STEP_ATTENTE_VALIDATION_RAPPORT => collect([$mission->demandeur])->filter(),
            default => collect(),
        };

        return $responsables->unique('id');
    }

    public static function notifyResponsablesEtapeCourante(Mission $mission, ?string $etapeLabel = null): void
    {
        $etapeLabel ??= self::libelleEtapeStatique($mission->current_step);
        $url = self::urlPourEtape($mission);

        foreach (self::responsablesPourEtape($mission) as $user) {
            self::notifyEtape($mission, $user, $etapeLabel, $url);
        }
    }

    public static function envoyerRappelsEtapeCourante(Mission $mission): void
    {
        $etapeLabel = self::libelleEtapeStatique($mission->current_step);
        $url = self::urlPourEtape($mission);

        foreach (self::responsablesPourEtape($mission) as $user) {
            self::notifyRappelEtape($mission, $user, $etapeLabel, $url);
        }
    }

    public static function notifyRapportSoumis(Mission $mission): void
    {
        $mission->loadMissing('demandeur');
        $demandeur = $mission->demandeur;

        if ($demandeur === null || empty($demandeur->email)) {
            return;
        }

        $numero = self::libelleNumeroMission($mission);

        $data = [
            'mission_id' => $mission->id,
            'numero_mission' => $numero,
            'objet' => $mission->objet,
            'signataire' => $mission->rapport_signataire_nom ?? '—',
            'action_url' => route('missions.show', $mission),
        ];

        SendEmail::dispatch(
            "Rapport de mission N° {$numero} à valider",
            $demandeur->email,
            [],
            [],
            'emails.missions.rapport-soumis',
            $data,
        );

        $demandeur->notify(new MissionOrdreGenereNotification(
            $mission->id,
            $mission->objet,
            "Le rapport de mission « {$mission->objet} » attend votre validation.",
            route('missions.show', $mission),
        ));
    }

    public static function notifyParticipantsConcernes(Mission $mission, string $etapeLabel, ?string $message = null): void
    {
        $mission->loadMissing(['demandeur', 'participants']);
        $url = route('missions.show', $mission);
        $texte = $message ?? 'Consultez la demande pour suivre son avancement dans le circuit de validation.';

        $numero = self::libelleNumeroMission($mission);

        foreach ($mission->participants as $participant) {
            if (empty($participant->email)) {
                continue;
            }

            $data = [
                'mission_id' => $mission->id,
                'numero_mission' => $numero,
                'objet' => $mission->objet,
                'etape' => $etapeLabel,
                'action_url' => $url,
                'demandeur' => $mission->demandeur?->name ?? '—',
                'message' => $texte,
            ];

            SendEmail::dispatch(
                "Mission N° {$numero} — Suivi pour missionnaire",
                $participant->email,
                [],
                [],
                'emails.missions.suivi-participant',
                $data,
            );

            $participant->notify(new MissionOrdreGenereNotification(
                $mission->id,
                $mission->objet,
                "Vous êtes missionnaire sur « {$mission->objet} » — {$etapeLabel}",
                $url,
            ));
        }
    }

    public static function notifyResponsableRhPourSignature(Mission $mission, User $generateur): void
    {
        $mission->loadMissing(['demandeur']);
        $pdfUrl = route('missions.pdf', $mission);
        $missionUrl = route('missions.show', $mission);

        $rrhUsers = self::utilisateursParRole('responsable_rh');

        $numero = self::libelleNumeroMission($mission);

        foreach ($rrhUsers as $rrh) {
            if (empty($rrh->email)) {
                continue;
            }

            $data = [
                'mission_id' => $mission->id,
                'numero_mission' => $numero,
                'objet' => $mission->objet,
                'pdf_url' => $pdfUrl,
                'mission_url' => $missionUrl,
                'generateur' => $generateur->name,
                'destinataire' => $rrh->name,
            ];

            try {
                SendEmail::dispatch(
                    "Signature électronique — Ordre de mission N° {$numero}",
                    $rrh->email,
                    [],
                    [],
                    'emails.missions.signature-rh',
                    $data,
                );

                $rrh->notify(new MissionOrdreGenereNotification(
                    $mission->id,
                    $mission->objet,
                    "L'ordre de mission « {$mission->objet} » attend votre signature électronique (Responsable RH).",
                    $missionUrl,
                ));
            } catch (\Throwable $e) {
                Log::channel('single')->error('Échec notification signature RH ordre mission', [
                    'mission_id' => $mission->id,
                    'user_id' => $rrh->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public static function notifyOrdresGeneres(Mission $mission): void
    {
        $mission->loadMissing(['demandeur', 'participants', 'chauffeur']);
        $pdfUrl = route('missions.pdf', $mission);

        $destinataires = collect([$mission->demandeur])
            ->merge($mission->participants)
            ->merge($mission->chauffeur ? [$mission->chauffeur] : [])
            ->filter()
            ->unique('id');

        $numero = self::libelleNumeroMission($mission);

        foreach ($destinataires as $user) {
            if (empty($user->email)) {
                continue;
            }

            $data = [
                'mission_id' => $mission->id,
                'numero_mission' => $numero,
                'objet' => $mission->objet,
                'pdf_url' => $pdfUrl,
                'destinataire' => $user->name,
            ];

            try {
                SendEmail::dispatch(
                    "Ordre de mission N° {$numero} disponible",
                    $user->email,
                    [],
                    [],
                    'emails.missions.ordre-genere',
                    $data,
                );

                $user->notify(new MissionOrdreGenereNotification(
                    $mission->id,
                    $mission->objet,
                    "L'ordre de mission « {$mission->objet} » a été généré et est disponible dans votre espace.",
                    $pdfUrl,
                ));
            } catch (\Throwable $e) {
                Log::channel('single')->error('Échec notification ordre mission', [
                    'mission_id' => $mission->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public static function notifyClotureMission(Mission $mission): void
    {
        $mission->loadMissing(['demandeur', 'participants', 'chauffeur']);
        $url = route('missions.show', $mission);
        $etapeLabel = 'Mission clôturée officiellement';

        $destinataires = collect([$mission->demandeur])
            ->merge($mission->participants)
            ->merge($mission->chauffeur ? [$mission->chauffeur] : [])
            ->filter()
            ->unique('id');

        foreach ($destinataires as $user) {
            if (empty($user->email)) {
                continue;
            }

            self::notifyEtape($mission, $user, $etapeLabel, $url);
        }
    }

    private static function urlPourEtape(Mission $mission): string
    {
        return match ($mission->current_step) {
            Mission::STEP_ATTENTE_DGA => route('missions.validation-dga'),
            Mission::STEP_ATTENTE_MD => route('missions.validation-md'),
            Mission::STEP_ATTENTE_FACILITIES => route('missions.validation-facilities'),
            Mission::STEP_ATTENTE_RH, 'ATTENTE_RH_LOGISTIQUE' => route('missions.validation-rh-logistique'),
            Mission::STEP_ATTENTE_SIGNATURE_RRH => route('missions.validation-signature-rrh'),
            Mission::STEP_ATTENTE_RAPPORT, Mission::STEP_ATTENTE_VALIDATION_RAPPORT => route('missions.rapports'),
            default => route('missions.show', $mission),
        };
    }

    public static function libelleEtapeStatique(string $step): string
    {
        return match ($step) {
            Mission::STEP_BROUILLON => 'Brouillon',
            Mission::STEP_ATTENTE_N1 => 'En attente de validation N+1',
            Mission::STEP_ATTENTE_DGA => 'En attente de validation DGA',
            Mission::STEP_ATTENTE_MD => 'En attente de signature DG',
            Mission::STEP_ATTENTE_FACILITIES => 'En attente de traitement Facilities',
            Mission::STEP_ATTENTE_RH, 'ATTENTE_RH_LOGISTIQUE' => 'En attente de validation RH',
            Mission::STEP_ATTENTE_SIGNATURE_RRH => 'En attente de signature Responsable RH',
            Mission::STEP_VALIDEE => 'Validée — ordres de mission signés',
            Mission::STEP_ATTENTE_RAPPORT => 'En attente du rapport de mission',
            Mission::STEP_ATTENTE_VALIDATION_RAPPORT => 'En attente de validation du rapport',
            Mission::STEP_CLOTUREE => 'Mission clôturée',
            default => $step,
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private static function utilisateursParRole(string $slug): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', $slug))
            ->orWhereHas('profil.roles', fn ($q) => $q->where('slug', $slug))
            ->get()
            ->unique('id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private static function missionnairesUsers(Mission $mission): \Illuminate\Support\Collection
    {
        $users = $mission->participants->filter(fn (User $u) => ! empty($u->email));

        $profilUsers = MissionParticipant::query()
            ->with('user')
            ->where('mission_id', $mission->id)
            ->where('role_dans_mission', 'missionnaire')
            ->whereNotNull('user_id')
            ->get()
            ->map(fn (MissionParticipant $p) => $p->user)
            ->filter(fn (?User $u) => $u !== null && ! empty($u->email));

        return $users->merge($profilUsers)->unique('id');
    }
}
