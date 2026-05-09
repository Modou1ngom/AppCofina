<?php

namespace App\Http\Controllers;

use App\Models\AvanceSalaireBareme;
use App\Models\AvanceSalaireDemande;
use App\Models\Profil;
use App\Services\AvanceSalaireCalculService;
use App\Services\AvanceSalaireIntegrationExterneService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AvanceSalaireDemandeController extends Controller
{
    public function __construct(
        private AvanceSalaireCalculService $calculService,
        private AvanceSalaireIntegrationExterneService $integrationExterneService,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $user->profilCollaborateurAssocie();
        if (! $user->profil && ! $this->peutVoirSesDemandesAvanceSansProfil($user)) {
            return redirect()->route('dashboard')->with('error', 'Un profil collaborateur est requis pour accéder aux avances sur salaire.');
        }

        $demandes = AvanceSalaireDemande::query()
            ->when(
                $user->profil !== null,
                fn ($q) => $q->where('profile_id', $user->profil->id),
                fn ($q) => $q->where('user_id', $user->id),
            )
            ->with(['profile' => static fn ($q) => $q->select('id', 'statut_rh', 'numero_compte')])
            ->orderByDesc('created_at')
            ->paginate(12)
            ->through(fn (AvanceSalaireDemande $d) => $this->toListRow($d));

        return Inertia::render('avances-salaire/Index', [
            'demandes' => $demandes,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $user->profilCollaborateurAssocie();
        if (! $user->profil) {
            $message = $this->peutVoirSesDemandesAvanceSansProfil($user)
                ? 'Pour créer une demande d’avance, votre compte doit correspondre à une fiche collaborateur (même e-mail que dans l’enrôlement staff).'
                : 'Un profil collaborateur est requis pour créer une demande d\'avance.';

            return redirect()->route('avances-salaire.index')->with('error', $message);
        }

        $p = $user->profil;
        $plafondPct = (float) config('avance_salaire.plafond_pct_defaut', 30);

        return Inertia::render('avances-salaire/Create', [
            'profil' => [
                'id' => $p->id,
                'matricule' => $p->matricule,
                'nom' => $p->nom,
                'prenom' => $p->prenom,
                'type_contrat' => $p->type_contrat,
                'departement' => $p->departement,
                'date_entree' => $p->date_entree?->format('Y-m-d'),
                'numero_compte' => $p->numero_compte,
                'statut_rh' => $p->statut_rh,
                'categorie_staff_suggeree' => $this->mapStatutRhToCategorie($p->statut_rh),
            ],
            'baremes' => $this->baremesPourFront(),
            'defaults' => [
                'plafond_pct' => $plafondPct,
                'duree_mois_min' => (int) config('avance_salaire.duree_mois_min', 1),
                'duree_mois_max' => (int) config('avance_salaire.duree_mois_max', 6),
                'anciennete_mois_min' => (int) config('avance_salaire.anciennete_mois_min', 6),
                'taux_interet_defaut' => 0,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->profilCollaborateurAssocie();
        if (! $user->profil) {
            $message = $this->peutVoirSesDemandesAvanceSansProfil($user)
                ? 'Pour créer une demande d’avance, votre compte doit correspondre à une fiche collaborateur (même e-mail que dans l’enrôlement staff).'
                : 'Profil collaborateur requis.';

            return redirect()->route('avances-salaire.index')->with('error', $message);
        }

        $typesKeys = array_keys($this->baremesMap());

        $validated = $request->validate([
            'type_avance' => ['required', Rule::in($typesKeys)],
            'mode_paiement' => ['nullable', Rule::in(['par_mois', 'par_tranche'])],
            'dates_tranches' => ['nullable', 'array', 'max:36'],
            'dates_tranches.*' => ['nullable', 'date'],
            'categorie_staff' => ['required', Rule::in(['non_cadre', 'cadre', 'emc'])],
            'compte_staff' => ['nullable', 'string', 'max:64'],
            'nombre_avance_en_cours' => ['nullable', 'integer', 'min:0', 'max:50'],
            'montant' => ['required', 'numeric', 'min:1'],
            'duree_mois' => ['required', 'integer', 'min:1', 'max:12'],
            'date_premiere_echeance' => ['required', 'date'],
            'salaire_net' => ['nullable', 'numeric', 'min:0'],
            'salaire_domicilie' => ['nullable', 'boolean'],
            'taux_interet_annuel_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $profil = $user->profil;
        $plafondPct = (float) config('avance_salaire.plafond_pct_defaut', 30);
        $compteStaff = $this->compteStaffFromProfilPrioritaire($profil, $validated['compte_staff'] ?? null);
        $categorieStaff = $this->categorieStaffFromProfilPrioritaire($profil, $validated['categorie_staff']);
        $modePaiement = $this->resolveModePaiementPourType($validated['type_avance'], $validated['mode_paiement'] ?? null);
        $bareme = $this->resolveBareme($validated['type_avance'], $categorieStaff);
        $dMin = (int) config('avance_salaire.duree_mois_min', 1);
        $validated['duree_mois'] = min($bareme['duree_max_mois'], max($dMin, (int) $validated['duree_mois']));
        $datesTranches = $this->normalizeDatesTranches($modePaiement, $validated['dates_tranches'] ?? []);

        $elig = $this->calculService->evaluerEligibilite(
            $profil,
            (float) $validated['montant'],
            $validated['duree_mois'],
            (float) ($validated['salaire_net'] ?? 0),
            (bool) ($validated['salaire_domicilie'] ?? false),
            $plafondPct,
            null,
            $bareme['plafond_montant'],
            $bareme['duree_max_mois'],
        );

        if (! $elig['eligible']) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'eligibilite' => implode(' ', $elig['messages']),
                ]);
        }

        $premiere = Carbon::parse($validated['date_premiere_echeance']);
        $taux = (float) ($validated['taux_interet_annuel_pct'] ?? 0);
        $sim = $this->calculService->simuler(
            (float) $validated['montant'],
            $validated['duree_mois'],
            $premiere,
            $taux,
        );

        $demande = new AvanceSalaireDemande([
            'user_id' => $user->id,
            'profile_id' => $profil->id,
            'matricule' => $profil->matricule ?? '',
            'nom' => $profil->nom,
            'prenom' => $profil->prenom,
            'type_avance' => $validated['type_avance'],
            'mode_paiement' => $modePaiement,
            'dates_tranches' => $datesTranches,
            'categorie_staff' => $categorieStaff,
            'montant' => $validated['montant'],
            'duree_mois' => $validated['duree_mois'],
            'compte_staff' => $compteStaff,
            'nombre_avance_en_cours' => (int) ($validated['nombre_avance_en_cours'] ?? 0),
            'date_premiere_echeance' => $premiere->format('Y-m-d'),
            'salaire_net' => (float) ($validated['salaire_net'] ?? 0),
            'salaire_domicilie' => (bool) ($validated['salaire_domicilie'] ?? false),
            'taux_interet_annuel_pct' => $taux,
            'plafond_pct_applique' => $elig['plafond_pct'],
            'montant_max_autorise' => $elig['montant_max'],
            'eligible' => $elig['eligible'],
            'eligibilite_messages' => $elig['messages'],
            'mensualite' => $sim['mensualite'],
            'date_fin_prevue' => $sim['date_fin']->format('Y-m-d'),
            'tableau_amortissement' => $sim['tableau'],
            'statut' => AvanceSalaireDemande::STATUT_SOUMISE,
            'filiale_id' => $profil->filiale_id,
        ]);
        $demande->save();

        return redirect()->route('avances-salaire.show', $demande)->with('success', 'Demande validée et transmise aux RH.');
    }

    public function show(Request $request, AvanceSalaireDemande $avance_salaire_demande): Response|RedirectResponse
    {
        $this->authorizeView($request->user(), $avance_salaire_demande);
        $avance_salaire_demande->load([
            'rhDecidedBy:id,name',
            'financeDecidedBy:id,name',
            'cfoValidatedBy:id,name',
            'mdValidatedBy:id,name',
            'rhPriseEnChargeBy:id,name',
            'rhTraitementTermineBy:id,name',
            'signatureEmployeBy:id,name',
            'signatureRhBy:id,name',
            'signatureFinanceBy:id,name',
            'profile',
        ]);

        $plafondPct = (float) config('avance_salaire.plafond_pct_defaut', 30);

        return Inertia::render('avances-salaire/Show', [
            'demande' => $this->toDetail($avance_salaire_demande),
            'baremes' => $this->baremesPourFront(),
            'profil' => [
                'type_contrat' => $avance_salaire_demande->profile?->type_contrat,
                'departement' => $avance_salaire_demande->profile?->departement,
                'date_entree' => $avance_salaire_demande->profile?->date_entree?->format('Y-m-d'),
                'numero_compte' => $avance_salaire_demande->profile?->numero_compte,
                'statut_rh' => $avance_salaire_demande->profile?->statut_rh,
                'categorie_staff_suggeree' => $this->mapStatutRhToCategorie($avance_salaire_demande->profile?->statut_rh),
            ],
            'defaults' => [
                'plafond_pct' => $plafondPct,
                'duree_mois_min' => (int) config('avance_salaire.duree_mois_min', 1),
                'duree_mois_max' => (int) config('avance_salaire.duree_mois_max', 6),
                'anciennete_mois_min' => (int) config('avance_salaire.anciennete_mois_min', 6),
            ],
            'isOwner' => $avance_salaire_demande->user_id === $request->user()->id,
            'canEdit' => $request->user()->isAdmin()
                || ($avance_salaire_demande->user_id === $request->user()->id && $avance_salaire_demande->isEditableByOwner()),
            'canSoumettre' => $avance_salaire_demande->user_id === $request->user()->id
                && $avance_salaire_demande->statut === AvanceSalaireDemande::STATUT_BROUILLON
                && $avance_salaire_demande->eligible,
            'canRh' => $this->canActRhOnDemande($request->user(), $avance_salaire_demande),
            'canFinance' => $this->canActFinanceOnDemande($request->user(), $avance_salaire_demande),
            'canReprendre' => $this->canReprendre($request->user(), $avance_salaire_demande),
            'canMarquerPriseEnChargeRh' => $this->canMarquerPriseEnChargeRh($request->user(), $avance_salaire_demande),
            'canTerminerTraitementRh' => $this->canTerminerTraitementRh($request->user(), $avance_salaire_demande),
            'canSignerEmploye' => $avance_salaire_demande->user_id === $request->user()->id && $this->signatureDisponible($avance_salaire_demande),
            'canSignerRh' => $this->canRh($request->user()) && $this->signatureDisponible($avance_salaire_demande),
            'canSignerFinance' => $this->canAccesValidationFinance($request->user())
                && $this->signatureDisponible($avance_salaire_demande)
                && $this->circuitFinanceRequis($avance_salaire_demande),
        ]);
    }

    public function update(Request $request, AvanceSalaireDemande $avance_salaire_demande): RedirectResponse
    {
        $isOwnerEditable = $avance_salaire_demande->user_id === $request->user()->id && $avance_salaire_demande->isEditableByOwner();
        if (! $request->user()->isAdmin() && ! $isOwnerEditable) {
            abort(403);
        }

        $typesKeys = array_keys($this->baremesMap());

        $validated = $request->validate([
            'type_avance' => ['required', Rule::in($typesKeys)],
            'mode_paiement' => ['nullable', Rule::in(['par_mois', 'par_tranche'])],
            'dates_tranches' => ['nullable', 'array', 'max:36'],
            'dates_tranches.*' => ['nullable', 'date'],
            'categorie_staff' => ['required', Rule::in(['non_cadre', 'cadre', 'emc'])],
            'compte_staff' => ['nullable', 'string', 'max:64'],
            'nombre_avance_en_cours' => ['nullable', 'integer', 'min:0', 'max:50'],
            'montant' => ['required', 'numeric', 'min:1'],
            'duree_mois' => ['required', 'integer', 'min:1', 'max:12'],
            'date_premiere_echeance' => ['required', 'date'],
            'salaire_net' => ['nullable', 'numeric', 'min:0'],
            'salaire_domicilie' => ['nullable', 'boolean'],
            'taux_interet_annuel_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $avance_salaire_demande->loadMissing('profile');
        $profil = $avance_salaire_demande->profile;
        if (! $profil) {
            abort(404);
        }

        $plafondPct = (float) config('avance_salaire.plafond_pct_defaut', 30);
        $compteStaff = $this->compteStaffFromProfilPrioritaire($profil, $validated['compte_staff'] ?? null);
        $categorieStaff = $this->categorieStaffFromProfilPrioritaire($profil, $validated['categorie_staff']);
        $modePaiement = $this->resolveModePaiementPourType($validated['type_avance'], $validated['mode_paiement'] ?? null);
        $bareme = $this->resolveBareme($validated['type_avance'], $categorieStaff);
        $dMin = (int) config('avance_salaire.duree_mois_min', 1);
        $validated['duree_mois'] = min($bareme['duree_max_mois'], max($dMin, (int) $validated['duree_mois']));
        $datesTranches = $this->normalizeDatesTranches($modePaiement, $validated['dates_tranches'] ?? []);

        $elig = $this->calculService->evaluerEligibilite(
            $profil,
            (float) $validated['montant'],
            $validated['duree_mois'],
            (float) ($validated['salaire_net'] ?? 0),
            (bool) ($validated['salaire_domicilie'] ?? false),
            $plafondPct,
            $avance_salaire_demande->id,
            $bareme['plafond_montant'],
            $bareme['duree_max_mois'],
        );

        $premiere = Carbon::parse($validated['date_premiere_echeance']);
        $taux = (float) ($validated['taux_interet_annuel_pct'] ?? 0);
        $sim = $this->calculService->simuler(
            (float) $validated['montant'],
            $validated['duree_mois'],
            $premiere,
            $taux,
        );

        $avance_salaire_demande->update([
            'type_avance' => $validated['type_avance'],
            'mode_paiement' => $modePaiement,
            'dates_tranches' => $datesTranches,
            'categorie_staff' => $categorieStaff,
            'compte_staff' => $compteStaff,
            'nombre_avance_en_cours' => (int) ($validated['nombre_avance_en_cours'] ?? 0),
            'montant' => $validated['montant'],
            'duree_mois' => $validated['duree_mois'],
            'date_premiere_echeance' => $premiere->format('Y-m-d'),
            'salaire_net' => (float) ($validated['salaire_net'] ?? 0),
            'salaire_domicilie' => (bool) ($validated['salaire_domicilie'] ?? false),
            'taux_interet_annuel_pct' => $taux,
            'plafond_pct_applique' => $elig['plafond_pct'],
            'montant_max_autorise' => $elig['montant_max'],
            'eligible' => $elig['eligible'],
            'eligibilite_messages' => $elig['messages'],
            'mensualite' => $sim['mensualite'],
            'date_fin_prevue' => $sim['date_fin']->format('Y-m-d'),
            'tableau_amortissement' => $sim['tableau'],
        ]);

        return redirect()->route('avances-salaire.show', $avance_salaire_demande)->with('success', 'Brouillon mis à jour.');
    }

    public function destroy(Request $request, AvanceSalaireDemande $avance_salaire_demande): RedirectResponse
    {
        if (! $request->user()->isAdmin()) {
            abort(403);
        }

        $avance_salaire_demande->delete();

        return redirect()->back()->with('success', 'Demande supprimée.');
    }

    public function soumettre(Request $request, AvanceSalaireDemande $avance_salaire_demande): RedirectResponse
    {
        if ($avance_salaire_demande->user_id !== $request->user()->id || $avance_salaire_demande->statut !== AvanceSalaireDemande::STATUT_BROUILLON) {
            abort(403);
        }

        $profil = $avance_salaire_demande->profile()->first();
        if (! $profil) {
            abort(404);
        }

        $compteStaff = $this->compteStaffFromProfilPrioritaire($profil, $avance_salaire_demande->compte_staff);
        $categorieStaff = $this->categorieStaffFromProfilPrioritaire($profil, $avance_salaire_demande->categorie_staff ?? 'non_cadre');
        $modePaiement = $this->resolveModePaiementPourType(
            $avance_salaire_demande->type_avance ?? 'salaire',
            $avance_salaire_demande->mode_paiement,
        );
        $datesTranches = $this->normalizeDatesTranches($modePaiement, $avance_salaire_demande->dates_tranches ?? []);

        $avance_salaire_demande->update([
            'compte_staff' => $compteStaff,
            'categorie_staff' => $categorieStaff,
            'mode_paiement' => $modePaiement,
            'dates_tranches' => $datesTranches,
        ]);
        $avance_salaire_demande->refresh();

        $plafondPct = (float) config('avance_salaire.plafond_pct_defaut', 30);
        $bareme = $this->resolveBareme(
            $avance_salaire_demande->type_avance ?? 'salaire',
            $categorieStaff,
        );
        $elig = $this->calculService->evaluerEligibilite(
            $profil,
            (float) $avance_salaire_demande->montant,
            (int) $avance_salaire_demande->duree_mois,
            (float) $avance_salaire_demande->salaire_net,
            (bool) $avance_salaire_demande->salaire_domicilie,
            $plafondPct,
            $avance_salaire_demande->id,
            $bareme['plafond_montant'],
            $bareme['duree_max_mois'],
        );

        if (! $elig['eligible']) {
            $avance_salaire_demande->update([
                'eligible' => false,
                'eligibilite_messages' => $elig['messages'],
            ]);

            return redirect()->back()->with('error', 'La demande n\'est pas éligible : corrigez les informations ou contactez les RH.');
        }

        $avance_salaire_demande->update([
            'eligible' => true,
            'eligibilite_messages' => $elig['messages'],
            'statut' => AvanceSalaireDemande::STATUT_SOUMISE,
        ]);

        return redirect()->route('avances-salaire.show', $avance_salaire_demande)->with('success', 'Demande soumise. Elle est en attente de validation RH.');
    }

    public function validationRh(Request $request): Response|RedirectResponse
    {
        if (! $this->canRh($request->user())) {
            abort(403);
        }

        $demandes = AvanceSalaireDemande::query()
            ->when(! $request->user()->isAdmin(), function ($q) use ($request) {
                $q->where('user_id', '!=', $request->user()->id);
            })
            ->where(function ($q) {
                $q->where('statut', AvanceSalaireDemande::STATUT_SOUMISE)
                    ->orWhere(function ($q2) {
                        $q2->where('statut', AvanceSalaireDemande::STATUT_EN_ATTENTE)
                            ->where('statut_avant_attente', AvanceSalaireDemande::STATUT_SOUMISE);
                    })
                    // Historique RH: demandes déjà validées/rejetées par les RH.
                    ->orWhereNotNull('rh_decided_at');
            })
            ->with(['profile' => static fn ($q) => $q->select('id', 'statut_rh', 'numero_compte')])
            ->orderBy('created_at')
            ->paginate(20)
            ->through(fn (AvanceSalaireDemande $d) => $this->toListRow($d));

        return Inertia::render('avances-salaire/ValidationRh', [
            'demandes' => $demandes,
        ]);
    }

    public function validationFinance(Request $request): Response|RedirectResponse
    {
        if (! $this->canAccesValidationFinance($request->user())) {
            abort(403);
        }

        $demandes = AvanceSalaireDemande::query()
            ->when(! $request->user()->isAdmin(), function ($q) use ($request) {
                $q->where('user_id', '!=', $request->user()->id);
            })
            ->where(function ($q) {
                $q->where('statut', AvanceSalaireDemande::STATUT_EN_VALIDATION_FINANCE)
                    ->orWhere(function ($q2) {
                        $q2->where('statut', AvanceSalaireDemande::STATUT_EN_ATTENTE)
                            ->where('statut_avant_attente', AvanceSalaireDemande::STATUT_EN_VALIDATION_FINANCE);
                    });
            })
            ->with(['profile' => static fn ($q) => $q->select('id', 'statut_rh', 'numero_compte')])
            ->orderBy('created_at')
            ->paginate(20)
            ->through(fn (AvanceSalaireDemande $d) => $this->toListRow($d));

        return Inertia::render('avances-salaire/ValidationFinance', [
            'demandes' => $demandes,
        ]);
    }

    /**
     * Demandes définitivement approuvées, en attente de traitement opérationnel par les RH (paiement / suivi).
     */
    public function priseEnChargeRh(Request $request): Response|RedirectResponse
    {
        if (! $this->canRh($request->user())) {
            abort(403);
        }

        $demandes = AvanceSalaireDemande::query()
            ->whereIn('statut', [
                AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
                AvanceSalaireDemande::STATUT_EN_COURS_TRAITEMENT,
            ])
            ->when(! $request->user()->isAdmin(), function ($q) use ($request) {
                $q->where('user_id', '!=', $request->user()->id);
            })
            ->with(['profile' => static fn ($q) => $q->select('id', 'statut_rh', 'numero_compte')])
            ->orderByDesc('finance_decided_at')
            ->orderByDesc('rh_decided_at')
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->through(fn (AvanceSalaireDemande $d) => $this->toListRow($d));

        $integrationTemplateLines = DB::table('avance_salaire_integration_lignes as l')
            ->join('avance_salaire_integrations as i', 'i.id', '=', 'l.integration_id')
            ->join('avance_salaire_demandes as d', 'd.id', '=', 'i.avance_salaire_demande_id')
            ->whereIn('d.statut', [
                AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
                AvanceSalaireDemande::STATUT_EN_COURS_TRAITEMENT,
                AvanceSalaireDemande::STATUT_TERMINEE,
            ])
            ->orderByDesc('l.id')
            ->limit(300)
            ->get([
                'l.numero',
                'l.no_batch',
                'l.no_compte',
                'l.sens',
                'l.montant',
                'l.code_operation',
                'l.date_de_valeur',
                'l.code_agence',
                'l.libelle_ecriture',
                'l.user_id',
                'l.annee_compte',
                'l.mois_compte',
            ])
            ->map(function ($row) {
                $dateValeur = null;
                if ($row->date_de_valeur !== null && $row->date_de_valeur !== '') {
                    try {
                        $dateValeur = Carbon::parse($row->date_de_valeur)->format('Y-m-d');
                    } catch (\Throwable) {
                        $dateValeur = (string) $row->date_de_valeur;
                    }
                }

                return [
                    'numero' => (int) $row->numero,
                    'no_batch' => (string) $row->no_batch,
                    'no_compte' => (string) $row->no_compte,
                    'sens' => (string) $row->sens,
                    'montant' => round((float) $row->montant, 2),
                    'code_operation' => $row->code_operation !== null ? (string) $row->code_operation : null,
                    'date_de_valeur' => $dateValeur,
                    'code_agence' => $row->code_agence !== null ? (string) $row->code_agence : null,
                    'libelle_ecriture' => $row->libelle_ecriture !== null ? (string) $row->libelle_ecriture : null,
                    'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                    'annee_compte' => $row->annee_compte !== null ? (int) $row->annee_compte : null,
                    'mois_compte' => $row->mois_compte !== null ? (int) $row->mois_compte : null,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('avances-salaire/PriseEnChargeRh', [
            'demandes' => $demandes,
            'integrationTemplateLines' => $integrationTemplateLines,
            'integrationExterne' => config('avance_salaire.integrations.mode') === 'external',
        ]);
    }

    /**
     * Envoie en une fois le tableau « template comptable » (miroir local) vers l’application tierce.
     */
    public function envoyerTemplateVersIntegrationExterne(Request $request): RedirectResponse
    {
        if (! $this->canRh($request->user())) {
            abort(403);
        }
        if (! $this->integrationExterneService->estActive()) {
            return redirect()->route('avances-salaire.integration-rh')->with(
                'error',
                'L’envoi API est désactivé : définissez AVANCE_SALAIRE_INTEGRATION_MODE=external et les variables AVANCE_SALAIRE_INTEGRATION_EXTERNAL_BASE_URL et AVANCE_SALAIRE_INTEGRATION_EXTERNAL_TOKEN.'
            );
        }

        $rows = DB::table('avance_salaire_integration_lignes as l')
            ->join('avance_salaire_integrations as i', 'i.id', '=', 'l.integration_id')
            ->join('avance_salaire_demandes as d', 'd.id', '=', 'i.avance_salaire_demande_id')
            ->whereIn('d.statut', [
                AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
                AvanceSalaireDemande::STATUT_EN_COURS_TRAITEMENT,
                AvanceSalaireDemande::STATUT_TERMINEE,
            ])
            ->orderByDesc('l.id')
            ->limit(300)
            ->get([
                'd.id as avance_salaire_demande_id',
                'l.numero',
                'l.no_batch',
                'l.no_compte',
                'l.sens',
                'l.montant',
                'l.code_operation',
                'l.date_de_valeur',
                'l.code_agence',
                'l.libelle_ecriture',
                'l.user_id',
                'l.annee_compte',
                'l.mois_compte',
            ]);

        $lignesPayload = $rows->map(function ($row) {
            $dateValeur = null;
            if ($row->date_de_valeur !== null && $row->date_de_valeur !== '') {
                try {
                    $dateValeur = Carbon::parse($row->date_de_valeur)->format('Y-m-d');
                } catch (\Throwable) {
                    $dateValeur = (string) $row->date_de_valeur;
                }
            }

            return [
                'avance_salaire_demande_id' => (int) $row->avance_salaire_demande_id,
                'numero' => (int) $row->numero,
                'no_batch' => (string) $row->no_batch,
                'no_compte' => (string) $row->no_compte,
                'sens' => (string) $row->sens,
                'montant' => round((float) $row->montant, 2),
                'code_operation' => $row->code_operation !== null ? (string) $row->code_operation : null,
                'date_de_valeur' => $dateValeur,
                'code_agence' => $row->code_agence !== null ? (string) $row->code_agence : null,
                'libelle_ecriture' => $row->libelle_ecriture !== null ? (string) $row->libelle_ecriture : null,
                'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                'annee_compte' => $row->annee_compte !== null ? (int) $row->annee_compte : null,
                'mois_compte' => $row->mois_compte !== null ? (int) $row->mois_compte : null,
            ];
        })->values()->all();

        if ($lignesPayload === []) {
            return redirect()->route('avances-salaire.integration-rh')->with(
                'error',
                'Aucune ligne de template à envoyer.'
            );
        }

        $user = $request->user();

        try {
            $templateComptable = [
                'intitule' => 'Template d’intégration (écritures à transmettre)',
                'nombre_lignes' => count($lignesPayload),
                'colonnes' => $this->templateComptableColonnes(),
                'lignes' => $this->lignesVersTemplateComptablePourIntegrationExterne($lignesPayload),
            ];

            $this->integrationExterneService->envoyer([
                'meta' => [
                    'source' => 'app-cofina',
                    'app_url' => (string) config('app.url'),
                ],
                'type_envoi' => 'synthese_template_integration_rh',
                'effectue_par' => [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ],
                'template_comptable' => $templateComptable,
                'lignes' => $lignesPayload,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('avances-salaire.integration-rh')->with(
                'error',
                $e instanceof \RuntimeException
                    ? $e->getMessage()
                    : 'Impossible de joindre l’application d’intégration. Réessayez ou contactez l’administrateur.'
            );
        }

        return redirect()->route('avances-salaire.integration-rh')->with(
            'success',
            'Template envoyé vers l’application d’intégration ('.count($lignesPayload).' ligne(s)).'
        );
    }

    public function marquerPriseEnChargeRh(Request $request, AvanceSalaireDemande $avance_salaire_demande): RedirectResponse
    {
        if (! $this->canRh($request->user())) {
            abort(403);
        }
        if ($this->isDemandeInitiator($request->user(), $avance_salaire_demande)) {
            abort(403);
        }
        if (! in_array($avance_salaire_demande->statut, [
            AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
            AvanceSalaireDemande::STATUT_EN_COURS_TRAITEMENT,
        ], true)) {
            return redirect()->back()->with('error', 'Seules les demandes en attente / en cours d’intégration peuvent être traitées via ce formulaire.');
        }

        $validated = $request->validate([
            'date_de_valeur' => ['nullable', 'date'],
            'code_operation' => ['nullable', 'string', 'max:32'],
            'libelle_ecriture' => ['nullable', 'string', 'max:255'],
            'ligne_credite.no_compte' => ['nullable', 'string', 'max:64'],
            'ligne_credite.sens' => ['nullable', Rule::in(['credite', 'debute'])],
            'ligne_credite.montant' => ['nullable', 'numeric', 'min:0'],
            'ligne_credite.code_agence' => ['nullable', 'string', 'max:255'],
            'ligne_debute.no_compte' => ['nullable', 'string', 'max:64'],
            'ligne_debute.sens' => ['nullable', Rule::in(['credite', 'debute'])],
            'ligne_debute.montant' => ['nullable', 'numeric', 'min:0'],
            'ligne_debute.code_agence' => ['nullable', 'string', 'max:255'],
        ]);
        $user = $request->user();
        $avance_salaire_demande->loadMissing('profile');

        $types = $this->baremesMap();
        $type = $types[$avance_salaire_demande->type_avance] ?? ($types['salaire'] ?? []);
        $label = $type['label'] ?? ($avance_salaire_demande->type_avance ?? 'Avance sur salaire');
        $compteCharge = isset($type['compte_charge']) && $type['compte_charge'] !== '' ? (string) $type['compte_charge'] : null;

        $dateDeValeur = $avance_salaire_demande->date_premiere_echeance?->format('Y-m-d');
        $anneeCompte = $avance_salaire_demande->date_premiere_echeance?->format('Y');
        $moisCompte = $avance_salaire_demande->date_premiere_echeance?->format('m');

        $codeAgence = $avance_salaire_demande->profile?->code_agence;
        $compteStaff = $avance_salaire_demande->compte_staff;
        $codeOperationParam = isset($type['code_operation']) ? trim((string) $type['code_operation']) : '';
        $integrationCodeOperation = trim((string) ($validated['code_operation'] ?? $codeOperationParam));
        $integrationCodeOperation = $integrationCodeOperation !== '' ? $integrationCodeOperation : null;
        $dateDeValeur = $validated['date_de_valeur'] ?? $dateDeValeur;
        $libelleEcriture = trim((string) ($validated['libelle_ecriture'] ?? $label)) ?: $label;
        $ligneCredite = $validated['ligne_credite'] ?? [];
        $ligneDebute = $validated['ligne_debute'] ?? [];

        $integration = DB::table('avance_salaire_integrations')
            ->where('avance_salaire_demande_id', $avance_salaire_demande->id)
            ->first();

        $noBatch = $integration?->no_batch;
        if (! $this->isNoBatchValide($noBatch)) {
            $noBatch = $this->genererNoBatch();
        }

        $externe = $this->integrationExterneService->estActive();

        $lignesPayload = [];
        if ($compteStaff !== null && trim((string) $compteStaff) !== '') {
            $lignesPayload[] = [
                'numero' => 1,
                'no_batch' => $noBatch,
                'no_compte' => (string) ($ligneCredite['no_compte'] ?? $compteStaff),
                'sens' => (string) ($ligneCredite['sens'] ?? 'credite'),
                'montant' => (float) ($ligneCredite['montant'] ?? (float) $avance_salaire_demande->montant),
                'code_operation' => $integrationCodeOperation,
                'date_de_valeur' => $dateDeValeur,
                'code_agence' => $ligneCredite['code_agence'] ?? $codeAgence,
                'libelle_ecriture' => $libelleEcriture,
                'annee_compte' => $anneeCompte ? (int) $anneeCompte : null,
                'mois_compte' => $moisCompte ? (int) $moisCompte : null,
                'user_id' => $user->id,
            ];
        }
        if ($compteCharge !== null && trim((string) $compteCharge) !== '') {
            $lignesPayload[] = [
                'numero' => 2,
                'no_batch' => $noBatch,
                'no_compte' => (string) ($ligneDebute['no_compte'] ?? $compteCharge),
                'sens' => (string) ($ligneDebute['sens'] ?? 'debute'),
                'montant' => (float) ($ligneDebute['montant'] ?? (float) $avance_salaire_demande->montant),
                'code_operation' => $integrationCodeOperation,
                'date_de_valeur' => $dateDeValeur,
                'code_agence' => $ligneDebute['code_agence'] ?? $codeAgence,
                'libelle_ecriture' => $libelleEcriture,
                'annee_compte' => $anneeCompte ? (int) $anneeCompte : null,
                'mois_compte' => $moisCompte ? (int) $moisCompte : null,
                'user_id' => $user->id,
            ];
        }

        if ($externe) {
            try {
                $templateComptable = [
                    'intitule' => 'Template comptable',
                    'nombre_lignes' => count($lignesPayload),
                    'colonnes' => $this->templateComptableColonnes(),
                    'lignes' => $this->lignesVersTemplateComptablePourIntegrationExterne($lignesPayload),
                ];

                $this->integrationExterneService->envoyer([
                    'meta' => [
                        'source' => 'app-cofina',
                        'app_url' => (string) config('app.url'),
                    ],
                    'avance_salaire_demande_id' => $avance_salaire_demande->id,
                    'effectue_par' => [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ],
                    'demande' => [
                        'matricule' => $avance_salaire_demande->matricule,
                        'nom' => $avance_salaire_demande->nom,
                        'prenom' => $avance_salaire_demande->prenom,
                        'montant' => (float) $avance_salaire_demande->montant,
                        'type_avance' => $avance_salaire_demande->type_avance,
                        'date_premiere_echeance' => $avance_salaire_demande->date_premiere_echeance?->format('Y-m-d'),
                        'compte_staff' => $compteStaff,
                        'code_agence' => $codeAgence,
                    ],
                    'integration' => [
                        'no_batch' => $noBatch,
                        'code_operation' => $integrationCodeOperation,
                        'libelle_ecriture' => $libelleEcriture,
                        'date_de_valeur' => $dateDeValeur,
                        'annee_compte' => $anneeCompte ? (int) $anneeCompte : null,
                        'mois_compte' => $moisCompte ? (int) $moisCompte : null,
                        'statut' => 'en_cours_traitement',
                    ],
                    'template_comptable' => $templateComptable,
                    'lignes' => $lignesPayload,
                ]);
            } catch (\Throwable $e) {
                report($e);

                return redirect()->back()->with(
                    'error',
                    $e instanceof \RuntimeException
                        ? $e->getMessage()
                        : 'Impossible de joindre l’application d’intégration. Réessayez ou contactez l’administrateur.'
                );
            }
        }

        // Le template d'intégration doit toujours refléter les données après intégration.
        // On conserve donc systématiquement un miroir local des lignes,
        // même quand l'envoi externe est activé.
        $persistIntegrationLocal = true;

        return DB::transaction(function () use (
            $user,
            $avance_salaire_demande,
            $integrationCodeOperation,
            $dateDeValeur,
            $libelleEcriture,
            $ligneCredite,
            $ligneDebute,
            $codeAgence,
            $compteStaff,
            $compteCharge,
            $anneeCompte,
            $moisCompte,
            $noBatch,
            $persistIntegrationLocal
        ) {
            if ($avance_salaire_demande->statut === AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE) {
                $avance_salaire_demande->update([
                    'statut' => AvanceSalaireDemande::STATUT_EN_COURS_TRAITEMENT,
                    'rh_prise_en_charge_at' => now(),
                    'rh_prise_en_charge_by' => $user->id,
                ]);
            }

            if (! $persistIntegrationLocal) {
                return redirect()->route('avances-salaire.integration-rh')->with(
                    'success',
                    'Dossier transmis à l’application d’intégration. Le traitement opérationnel se poursuit sur cette application.'
                );
            }

            $integration = DB::table('avance_salaire_integrations')
                ->where('avance_salaire_demande_id', $avance_salaire_demande->id)
                ->first();

            if (! $integration) {
                $integrationId = DB::table('avance_salaire_integrations')->insertGetId([
                    'avance_salaire_demande_id' => $avance_salaire_demande->id,
                    'no_batch' => $noBatch,
                    'code_operation' => $integrationCodeOperation,
                    'libelle_ecriture' => $libelleEcriture,
                    'date_de_valeur' => $dateDeValeur,
                    'annee_compte' => $anneeCompte ? (int) $anneeCompte : null,
                    'mois_compte' => $moisCompte ? (int) $moisCompte : null,
                    'statut' => 'en_cours_traitement',
                    'created_by' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $integrationId = (int) $integration->id;
                DB::table('avance_salaire_integrations')
                    ->where('id', $integrationId)
                    ->update([
                        'no_batch' => $noBatch,
                        'code_operation' => $integrationCodeOperation,
                        'libelle_ecriture' => $libelleEcriture,
                        'date_de_valeur' => $dateDeValeur,
                        'statut' => 'en_cours_traitement',
                        'updated_at' => now(),
                    ]);
            }

            if ($compteStaff !== null && trim((string) $compteStaff) !== '') {
                DB::table('avance_salaire_integration_lignes')->updateOrInsert(
                    ['integration_id' => $integrationId, 'numero' => 1],
                    [
                        'no_batch' => $noBatch,
                        'no_compte' => (string) ($ligneCredite['no_compte'] ?? $compteStaff),
                        'sens' => (string) ($ligneCredite['sens'] ?? 'credite'),
                        'montant' => (float) ($ligneCredite['montant'] ?? (float) $avance_salaire_demande->montant),
                        'code_operation' => $integrationCodeOperation,
                        'date_de_valeur' => $dateDeValeur,
                        'code_agence' => $ligneCredite['code_agence'] ?? $codeAgence,
                        'libelle_ecriture' => $libelleEcriture,
                        'annee_compte' => $anneeCompte ? (int) $anneeCompte : null,
                        'mois_compte' => $moisCompte ? (int) $moisCompte : null,
                        'user_id' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            if ($compteCharge !== null && trim((string) $compteCharge) !== '') {
                DB::table('avance_salaire_integration_lignes')->updateOrInsert(
                    ['integration_id' => $integrationId, 'numero' => 2],
                    [
                        'no_batch' => $noBatch,
                        'no_compte' => (string) ($ligneDebute['no_compte'] ?? $compteCharge),
                        'sens' => (string) ($ligneDebute['sens'] ?? 'debute'),
                        'montant' => (float) ($ligneDebute['montant'] ?? (float) $avance_salaire_demande->montant),
                        'code_operation' => $integrationCodeOperation,
                        'date_de_valeur' => $dateDeValeur,
                        'code_agence' => $ligneDebute['code_agence'] ?? $codeAgence,
                        'libelle_ecriture' => $libelleEcriture,
                        'annee_compte' => $anneeCompte ? (int) $anneeCompte : null,
                        'mois_compte' => $moisCompte ? (int) $moisCompte : null,
                        'user_id' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            return redirect()->route('avances-salaire.integration-rh')->with('success', 'Intégration RH démarrée. Écritures générées (lignes).');
        });
    }

    public function integrationRhForm(Request $request, AvanceSalaireDemande $avance_salaire_demande): Response|RedirectResponse
    {
        if (! $this->canRh($request->user())) {
            abort(403);
        }
        if ($this->isDemandeInitiator($request->user(), $avance_salaire_demande)) {
            abort(403);
        }
        if (! in_array($avance_salaire_demande->statut, [
            AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
            AvanceSalaireDemande::STATUT_EN_COURS_TRAITEMENT,
        ], true)) {
            return redirect()->route('avances-salaire.integration-rh')
                ->with('error', 'Ce dossier n’est plus en attente d’intégration.');
        }

        $avance_salaire_demande->loadMissing('profile');
        $types = $this->baremesMap();
        $type = $types[$avance_salaire_demande->type_avance] ?? ($types['salaire'] ?? []);
        $label = $type['label'] ?? ($avance_salaire_demande->type_avance ?? 'Avance sur salaire');
        $compteCharge = isset($type['compte_charge']) && $type['compte_charge'] !== '' ? (string) $type['compte_charge'] : '';
        $codeOperation = isset($type['code_operation']) ? trim((string) $type['code_operation']) : '';
        $integration = DB::table('avance_salaire_integrations')
            ->where('avance_salaire_demande_id', $avance_salaire_demande->id)
            ->first();
        $lignes = collect();
        if ($integration) {
            $lignes = DB::table('avance_salaire_integration_lignes')
                ->where('integration_id', $integration->id)
                ->orderBy('numero')
                ->get();
        }
        $ligneCredite = $lignes->firstWhere('numero', 1);
        $ligneDebute = $lignes->firstWhere('numero', 2);

        return Inertia::render('avances-salaire/IntegrationRhForm', [
            'integrationExterne' => config('avance_salaire.integrations.mode') === 'external',
            'demande' => [
                'id' => $avance_salaire_demande->id,
                'nom' => $avance_salaire_demande->nom,
                'prenom' => $avance_salaire_demande->prenom,
                'matricule' => $avance_salaire_demande->matricule,
                'montant' => (float) $avance_salaire_demande->montant,
                'date_de_valeur' => $integration->date_de_valeur ?? $avance_salaire_demande->date_premiere_echeance?->format('Y-m-d'),
                'compte_staff' => $ligneCredite?->no_compte ?? $avance_salaire_demande->compte_staff,
                'code_agence' => $ligneCredite?->code_agence ?? $avance_salaire_demande->profile?->code_agence,
                'compte_charge' => $ligneDebute?->no_compte ?? $compteCharge,
                'libelle_ecriture' => $integration->libelle_ecriture ?? $label,
                'code_operation' => $integration->code_operation ?? $codeOperation,
                'ligne_credite_sens' => $ligneCredite?->sens ?? 'credite',
                'ligne_debute_sens' => $ligneDebute?->sens ?? 'debute',
                'ligne_credite_montant' => $ligneCredite?->montant ?? (float) $avance_salaire_demande->montant,
                'ligne_debute_montant' => $ligneDebute?->montant ?? (float) $avance_salaire_demande->montant,
                'ligne_debute_code_agence' => $ligneDebute?->code_agence ?? $avance_salaire_demande->profile?->code_agence,
            ],
        ]);
    }

    public function terminerTraitementRh(Request $request, AvanceSalaireDemande $avance_salaire_demande): RedirectResponse
    {
        if (! $this->canRh($request->user())) {
            abort(403);
        }
        if ($this->isDemandeInitiator($request->user(), $avance_salaire_demande)) {
            abort(403);
        }
        if ($avance_salaire_demande->statut !== AvanceSalaireDemande::STATUT_EN_COURS_TRAITEMENT) {
            return redirect()->back()->with('error', 'Seules les demandes en cours d’intégration peuvent être clôturées.');
        }
        if ($avance_salaire_demande->rh_traitement_termine_at !== null) {
            return redirect()->back()->with('error', 'L’intégration est déjà terminée.');
        }

        $avance_salaire_demande->update([
            'statut' => AvanceSalaireDemande::STATUT_TERMINEE,
            'rh_traitement_termine_at' => now(),
            'rh_traitement_termine_by' => $request->user()->id,
        ]);

        DB::table('avance_salaire_integrations')
            ->where('avance_salaire_demande_id', $avance_salaire_demande->id)
            ->update([
                'statut' => 'terminee',
                'updated_at' => now(),
            ]);

        return redirect()->back()->with('success', 'Intégration terminée.');
    }

    public function decisionRh(Request $request, AvanceSalaireDemande $avance_salaire_demande): RedirectResponse
    {
        if (! $this->canActRhOnDemande($request->user(), $avance_salaire_demande)) {
            abort(403);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approuve,rejete,attente'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'suite_rh' => ['nullable', 'in:cloture_rh,transmettre_cfo,transmettre_md'],
        ]);

        if ($validated['decision'] === 'approuve') {
            $suite = $validated['suite_rh'] ?? null;
            if (! in_array($suite, ['cloture_rh', 'transmettre_cfo', 'transmettre_md'], true)) {
                throw ValidationException::withMessages([
                    'suite_rh' => 'Indiquez si la demande est close au niveau RH, transmise au CFO uniquement, ou jusqu’au MD (CFO puis MD).',
                ]);
            }
        }

        return $this->applyRhDecision(
            $avance_salaire_demande,
            $request->user(),
            $validated['decision'],
            $validated['commentaire'] ?? null,
            $validated['suite_rh'] ?? null,
        );
    }

    public function decisionFinance(Request $request, AvanceSalaireDemande $avance_salaire_demande): RedirectResponse
    {
        if (! $this->canActFinanceOnDemande($request->user(), $avance_salaire_demande)) {
            abort(403);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approuve,rejete,attente'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->applyFinanceDecision($avance_salaire_demande, $request->user(), $validated['decision'], $validated['commentaire'] ?? null);
    }

    public function reprendre(Request $request, AvanceSalaireDemande $avance_salaire_demande): RedirectResponse
    {
        if ($avance_salaire_demande->statut !== AvanceSalaireDemande::STATUT_EN_ATTENTE || ! $avance_salaire_demande->statut_avant_attente) {
            abort(403);
        }

        $user = $request->user();
        $prev = $avance_salaire_demande->statut_avant_attente;
        if ($prev === AvanceSalaireDemande::STATUT_SOUMISE && ! $this->canRh($user)) {
            abort(403);
        }
        if ($prev === AvanceSalaireDemande::STATUT_EN_VALIDATION_FINANCE && ! $this->canAccesValidationFinance($user)) {
            abort(403);
        }

        $avance_salaire_demande->update([
            'statut' => $prev,
            'statut_avant_attente' => null,
        ]);

        return redirect()->back()->with('success', 'La demande a été remise en traitement.');
    }

    public function signer(Request $request, AvanceSalaireDemande $avance_salaire_demande): RedirectResponse
    {
        $this->authorizeView($request->user(), $avance_salaire_demande);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['employe', 'rh', 'finance'])],
            'signature' => ['required', 'string', 'max:65000', 'regex:/^data:image\/(png|jpe?g|webp);base64,/'],
        ]);

        $type = $validated['type'];
        $user = $request->user();

        if ($type === 'employe' && (int) $avance_salaire_demande->user_id !== (int) $user->id) {
            abort(403);
        }

        if ($type === 'rh' && ! $this->canRh($user)) {
            abort(403);
        }

        if ($type === 'finance' && ! $this->canAccesValidationFinance($user)) {
            abort(403);
        }

        if (! $this->signatureDisponible($avance_salaire_demande)) {
            return redirect()->back()->with('error', 'La signature est disponible après décision sur la demande.');
        }

        if ($type === 'finance' && ! $this->circuitFinanceRequis($avance_salaire_demande)) {
            return redirect()->back()->with('error', 'Aucune signature CFO / MD n’est requise pour cette demande.');
        }

        $columns = match ($type) {
            'employe' => ['signature_employe', 'signature_employe_by', 'signature_employe_at'],
            'rh' => ['signature_rh', 'signature_rh_by', 'signature_rh_at'],
            'finance' => ['signature_finance', 'signature_finance_by', 'signature_finance_at'],
        };

        $avance_salaire_demande->update([
            $columns[0] => $validated['signature'],
            $columns[1] => $user->id,
            $columns[2] => now(),
        ]);

        return redirect()->back()->with('success', 'Signature enregistrée.');
    }

    private function canReprendre(\App\Models\User $user, AvanceSalaireDemande $demande): bool
    {
        if ($demande->statut !== AvanceSalaireDemande::STATUT_EN_ATTENTE || ! $demande->statut_avant_attente) {
            return false;
        }
        if ($demande->statut_avant_attente === AvanceSalaireDemande::STATUT_SOUMISE) {
            return $this->canRh($user) && ! $this->isDemandeInitiator($user, $demande);
        }
        if ($demande->statut_avant_attente === AvanceSalaireDemande::STATUT_EN_VALIDATION_FINANCE) {
            return $this->canAccesValidationFinance($user) && ! $this->isDemandeInitiator($user, $demande);
        }

        return false;
    }

    private function circuitFinanceRequis(AvanceSalaireDemande $demande): bool
    {
        return in_array($demande->rh_niveau_finance, ['cfo', 'md'], true)
            || $demande->finance_decided_at !== null
            || $demande->cfo_validated_at !== null
            || $demande->md_validated_at !== null;
    }

    private function signatureDisponible(AvanceSalaireDemande $demande): bool
    {
        return in_array($demande->statut, [
            AvanceSalaireDemande::STATUT_APPROUVEE,
            AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
            AvanceSalaireDemande::STATUT_EN_COURS_TRAITEMENT,
            AvanceSalaireDemande::STATUT_TERMINEE,
            AvanceSalaireDemande::STATUT_REJETEE,
        ], true);
    }

    private function applyRhDecision(AvanceSalaireDemande $demande, \App\Models\User $user, string $decision, ?string $commentaire, ?string $suiteRh = null): RedirectResponse
    {
        return DB::transaction(function () use ($demande, $user, $decision, $commentaire, $suiteRh) {
            if ($decision === 'approuve' && $suiteRh === 'cloture_rh') {
                $demande->update([
                    'statut' => AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
                    'statut_avant_attente' => null,
                    'rh_niveau_finance' => null,
                    'rh_decided_at' => now(),
                    'rh_decided_by' => $user->id,
                    'rh_commentaire' => $commentaire,
                ]);

                return redirect()->back()->with('success', 'Demande validée et clôturée au niveau RH. En attente d’intégration opérationnelle.');
            }
            if ($decision === 'approuve' && $suiteRh === 'transmettre_cfo') {
                $demande->update([
                    'statut' => AvanceSalaireDemande::STATUT_EN_VALIDATION_FINANCE,
                    'statut_avant_attente' => null,
                    'rh_niveau_finance' => 'cfo',
                    'cfo_validated_at' => null,
                    'cfo_validated_by' => null,
                    'cfo_commentaire' => null,
                    'md_validated_at' => null,
                    'md_validated_by' => null,
                    'md_commentaire' => null,
                    'rh_decided_at' => now(),
                    'rh_decided_by' => $user->id,
                    'rh_commentaire' => $commentaire,
                ]);

                return redirect()->back()->with('success', 'Demande transmise pour validation du CFO uniquement.');
            }
            if ($decision === 'approuve' && $suiteRh === 'transmettre_md') {
                $demande->update([
                    'statut' => AvanceSalaireDemande::STATUT_EN_VALIDATION_FINANCE,
                    'statut_avant_attente' => null,
                    'rh_niveau_finance' => 'md',
                    'cfo_validated_at' => null,
                    'cfo_validated_by' => null,
                    'cfo_commentaire' => null,
                    'md_validated_at' => null,
                    'md_validated_by' => null,
                    'md_commentaire' => null,
                    'rh_decided_at' => now(),
                    'rh_decided_by' => $user->id,
                    'rh_commentaire' => $commentaire,
                ]);

                return redirect()->back()->with('success', 'Demande transmise pour validation CFO puis MD.');
            }
            if ($decision === 'rejete') {
                $demande->update([
                    'statut' => AvanceSalaireDemande::STATUT_REJETEE,
                    'statut_avant_attente' => null,
                    'rh_decided_at' => now(),
                    'rh_decided_by' => $user->id,
                    'rh_commentaire' => $commentaire,
                ]);

                return redirect()->back()->with('success', 'Demande rejetée.');
            }

            $demande->update([
                'statut' => AvanceSalaireDemande::STATUT_EN_ATTENTE,
                'statut_avant_attente' => AvanceSalaireDemande::STATUT_SOUMISE,
                'rh_decided_at' => now(),
                'rh_decided_by' => $user->id,
                'rh_commentaire' => $commentaire,
            ]);

            return redirect()->back()->with('success', 'Demande mise en attente.');
        });
    }

    private function applyFinanceDecision(AvanceSalaireDemande $demande, \App\Models\User $user, string $decision, ?string $commentaire): RedirectResponse
    {
        return DB::transaction(function () use ($demande, $user, $decision, $commentaire) {
            $niveau = $demande->rh_niveau_finance;

            if ($decision === 'rejete') {
                $demande->update([
                    'statut' => AvanceSalaireDemande::STATUT_REJETEE,
                    'statut_avant_attente' => null,
                    'finance_decided_at' => now(),
                    'finance_decided_by' => $user->id,
                    'finance_commentaire' => $commentaire,
                ]);

                return redirect()->back()->with('success', 'Demande rejetée (CFO / MD).');
            }

            if ($decision === 'attente') {
                $demande->update([
                    'statut' => AvanceSalaireDemande::STATUT_EN_ATTENTE,
                    'statut_avant_attente' => AvanceSalaireDemande::STATUT_EN_VALIDATION_FINANCE,
                    'finance_decided_at' => now(),
                    'finance_decided_by' => $user->id,
                    'finance_commentaire' => $commentaire,
                ]);

                return redirect()->back()->with('success', 'Demande mise en attente par le CFO.');
            }

            if ($niveau === null || $niveau === '') {
                $demande->update([
                    'statut' => AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
                    'statut_avant_attente' => null,
                    'finance_decided_at' => now(),
                    'finance_decided_by' => $user->id,
                    'finance_commentaire' => $commentaire,
                ]);

                return redirect()->back()->with('success', 'Demande approuvée par le CFO. En attente d’intégration RH.');
            }

            if ($niveau === 'cfo') {
                $demande->update([
                    'cfo_validated_at' => now(),
                    'cfo_validated_by' => $user->id,
                    'cfo_commentaire' => $commentaire,
                    'statut' => AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
                    'statut_avant_attente' => null,
                ]);

                return redirect()->back()->with('success', 'Demande approuvée (validation CFO). En attente d’intégration RH.');
            }

            if ($niveau === 'md') {
                if ($demande->cfo_validated_at === null) {
                    $demande->update([
                        'cfo_validated_at' => now(),
                        'cfo_validated_by' => $user->id,
                        'cfo_commentaire' => $commentaire,
                    ]);

                    return redirect()->back()->with('success', 'Validation CFO enregistrée. La demande attend la validation du MD.');
                }

                $demande->update([
                    'md_validated_at' => now(),
                    'md_validated_by' => $user->id,
                    'md_commentaire' => $commentaire,
                    'statut' => AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
                    'statut_avant_attente' => null,
                ]);

                return redirect()->back()->with('success', 'Demande approuvée (CFO et MD). En attente d’intégration RH.');
            }

            $demande->update([
                'statut' => AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
                'statut_avant_attente' => null,
                'finance_decided_at' => now(),
                'finance_decided_by' => $user->id,
                'finance_commentaire' => $commentaire,
            ]);

            return redirect()->back()->with('success', 'Demande approuvée par le CFO. En attente d’intégration RH.');
        });
    }

    /** Tout utilisateur connecté peut consulter « Mes demandes » même sans profil lié (liste filtrée sur user_id). */
    private function peutVoirSesDemandesAvanceSansProfil(\App\Models\User $user): bool
    {
        return $user->exists;
    }

    private function isDemandeInitiator(\App\Models\User $user, AvanceSalaireDemande $demande): bool
    {
        return (int) $demande->user_id === (int) $user->id;
    }

    private function canActRhOnDemande(\App\Models\User $user, AvanceSalaireDemande $demande): bool
    {
        return $this->canRh($user)
            && $this->demandeEnFileRh($demande)
            && ! $this->isDemandeInitiator($user, $demande);
    }

    private function canActFinanceOnDemande(\App\Models\User $user, AvanceSalaireDemande $demande): bool
    {
        if (! $this->demandeEnFileFinance($demande) || $this->isDemandeInitiator($user, $demande)) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        $niveau = $demande->rh_niveau_finance;
        if ($niveau === 'md' && $demande->cfo_validated_at !== null) {
            return $user->isMd();
        }
        if ($niveau === 'md' && $demande->cfo_validated_at === null) {
            return $user->isFinance();
        }

        return $user->isFinance();
    }

    /** Accès file / pages validation finance (CFO et MD). */
    private function canAccesValidationFinance(\App\Models\User $user): bool
    {
        return $user->isAdmin() || $user->isFinance() || $user->isMd();
    }

    private function canMarquerPriseEnChargeRh(\App\Models\User $user, AvanceSalaireDemande $demande): bool
    {
        return $this->canRh($user)
            && ! $this->isDemandeInitiator($user, $demande)
            && $demande->statut === AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE;
    }

    private function canTerminerTraitementRh(\App\Models\User $user, AvanceSalaireDemande $demande): bool
    {
        return $this->canRh($user)
            && ! $this->isDemandeInitiator($user, $demande)
            && $demande->statut === AvanceSalaireDemande::STATUT_EN_COURS_TRAITEMENT;
    }

    private function canRh(\App\Models\User $user): bool
    {
        return $user->isAdmin() || $user->isRh();
    }

    private function demandeEnFileRh(AvanceSalaireDemande $demande): bool
    {
        if ($demande->statut === AvanceSalaireDemande::STATUT_SOUMISE) {
            return true;
        }
        if ($demande->statut === AvanceSalaireDemande::STATUT_EN_ATTENTE
            && $demande->statut_avant_attente === AvanceSalaireDemande::STATUT_SOUMISE) {
            return true;
        }

        return false;
    }

    private function demandeEnFileFinance(AvanceSalaireDemande $demande): bool
    {
        if ($demande->statut === AvanceSalaireDemande::STATUT_EN_VALIDATION_FINANCE) {
            return true;
        }
        if ($demande->statut === AvanceSalaireDemande::STATUT_EN_ATTENTE
            && $demande->statut_avant_attente === AvanceSalaireDemande::STATUT_EN_VALIDATION_FINANCE) {
            return true;
        }

        return false;
    }

    private function authorizeView(\App\Models\User $user, AvanceSalaireDemande $demande): void
    {
        $user->profilCollaborateurAssocie();

        if ($user->isAdmin()) {
            return;
        }
        if ($user->profil && $demande->profile_id === $user->profil->id) {
            return;
        }
        if ($this->canRh($user) && $this->demandeEnFileRh($demande)) {
            return;
        }
        if ($this->canAccesValidationFinance($user) && $this->demandeEnFileFinance($demande)) {
            return;
        }
        if ($this->canRh($user) && in_array($demande->statut, [
            AvanceSalaireDemande::STATUT_EN_VALIDATION_FINANCE,
            AvanceSalaireDemande::STATUT_APPROUVEE,
            AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
            AvanceSalaireDemande::STATUT_EN_COURS_TRAITEMENT,
            AvanceSalaireDemande::STATUT_TERMINEE,
            AvanceSalaireDemande::STATUT_REJETEE,
            AvanceSalaireDemande::STATUT_EN_ATTENTE,
        ], true)) {
            return;
        }
        if ($this->canAccesValidationFinance($user) && in_array($demande->statut, [
            AvanceSalaireDemande::STATUT_APPROUVEE,
            AvanceSalaireDemande::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
            AvanceSalaireDemande::STATUT_EN_COURS_TRAITEMENT,
            AvanceSalaireDemande::STATUT_TERMINEE,
            AvanceSalaireDemande::STATUT_REJETEE,
            AvanceSalaireDemande::STATUT_EN_ATTENTE,
        ], true)) {
            return;
        }

        abort(403);
    }

    private function libelleEtapeFinance(AvanceSalaireDemande $d): ?string
    {
        if ($d->statut !== AvanceSalaireDemande::STATUT_EN_VALIDATION_FINANCE) {
            return null;
        }

        return match ($d->rh_niveau_finance) {
            'cfo' => 'Les RH ont transmis le dossier pour validation du CFO uniquement.',
            'md' => $d->cfo_validated_at !== null
                ? 'Les RH ont transmis le dossier pour validation du MD (après le CFO).'
                : 'Les RH ont transmis le dossier pour validation du CFO, puis du MD.',
            default => 'Les RH ont transmis le dossier pour validation du CFO.',
        };
    }

    private function libelleCategorieStaff(?string $key): string
    {
        return match ($key ?? 'non_cadre') {
            'cadre' => 'Cadre',
            'emc' => 'EMC',
            default => 'Non cadre',
        };
    }

    private function toListRow(AvanceSalaireDemande $d): array
    {
        $categorie = $d->categorie_staff ?? 'non_cadre';

        return [
            'id' => $d->id,
            'user_id' => $d->user_id,
            'matricule' => $d->matricule,
            'nom' => $d->nom,
            'prenom' => $d->prenom,
            'montant' => (float) $d->montant,
            'statut' => $d->statut,
            'statut_label' => $d->libelleStatutWorkflow(),
            'statut_avant_attente' => $d->statut_avant_attente,
            'eligible' => $d->eligible,
            'created_at' => $d->created_at?->toIso8601String(),
            'statut_rh' => $d->profile?->statut_rh,
            'numero_compte' => $d->profile?->numero_compte,
            'categorie_staff' => $categorie,
            'categorie_staff_label' => $this->libelleCategorieStaff($categorie),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, compte_charge: string, duree_max_mois: int, plafonds: array<string, int>, modes_remboursement: array<int, string>, mode_remboursement_defaut: string}>
     */
    private function baremesPourFront(): array
    {
        $types = $this->baremesMap();
        $out = [];
        foreach ($types as $key => $t) {
            $modes = $this->modesRemboursementPourType($key);
            $out[] = [
                'key' => $key,
                'label' => $t['label'] ?? $key,
                'compte_charge' => $t['compte_charge'] ?? '',
                'duree_max_mois' => (int) ($t['duree_max_mois'] ?? 6),
                'plafonds' => $t['plafonds'] ?? [],
                'modes_remboursement' => $modes,
                'mode_remboursement_defaut' => $this->modeRemboursementDefautPourType($key, $modes),
            ];
        }

        return $out;
    }

    /**
     * @return array{duree_max_mois: int, plafond_montant: float}
     */
    private function resolveBareme(string $typeAvance, string $categorieStaff): array
    {
        $types = $this->baremesMap();
        $def = $types[$typeAvance] ?? $types['salaire'] ?? [];
        $plafonds = $def['plafonds'] ?? ['non_cadre' => 300_000];
        $cap = (float) ($plafonds[$categorieStaff] ?? $plafonds['non_cadre'] ?? 300_000);

        return [
            'duree_max_mois' => (int) ($def['duree_max_mois'] ?? (int) config('avance_salaire.duree_mois_max', 6)),
            'plafond_montant' => $cap,
        ];
    }

    /**
     * @return array<string, array{label: string, compte_charge: string, duree_max_mois: int, plafonds: array<string, int>}>
     */
    private function baremesMap(): array
    {
        if (Schema::hasTable('avance_salaire_baremes')) {
            $rows = AvanceSalaireBareme::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($rows->isNotEmpty()) {
                $mapped = [];
                foreach ($rows as $row) {
                    $mapped[$row->key] = [
                        'label' => $row->label,
                        'compte_charge' => $row->compte_charge ?? '',
                        'code_operation' => $row->code_operation ?? '',
                        'duree_max_mois' => (int) $row->duree_max_mois,
                        'plafonds' => [
                            'non_cadre' => (int) $row->plafond_non_cadre,
                            'cadre' => (int) $row->plafond_cadre,
                            'emc' => (int) $row->plafond_emc,
                        ],
                    ];
                }

                return $mapped;
            }
        }

        /** @var array<string, array{label: string, compte_charge: string, duree_max_mois: int, plafonds: array<string, int>}> $fallback */
        $fallback = config('avance_salaire.types', []);

        return $fallback;
    }

    /**
     * Colonne RH / import (ex. « Non Cadre ») → clé utilisée pour les barèmes d’avance.
     */
    private function mapStatutRhToCategorie(?string $statutRh): string
    {
        if ($statutRh === null || trim($statutRh) === '') {
            return 'non_cadre';
        }

        $s = Str::lower(Str::ascii(trim($statutRh)));
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        if (str_contains($s, 'non') && str_contains($s, 'cadre')) {
            return 'non_cadre';
        }

        if (str_contains($s, 'emc')) {
            return 'emc';
        }

        if (str_contains($s, 'cadre')) {
            return 'cadre';
        }

        return 'non_cadre';
    }

    private function compteStaffFromProfilPrioritaire(Profil $profil, ?string $requestCompte): ?string
    {
        $fromProfil = $profil->numero_compte;
        if ($fromProfil !== null && trim((string) $fromProfil) !== '') {
            return trim((string) $fromProfil);
        }

        if ($requestCompte === null) {
            return null;
        }

        $t = trim($requestCompte);

        return $t !== '' ? $t : null;
    }

    /**
     * @return array<string, string>
     */
    private function templateComptableColonnes(): array
    {
        return [
            'numero' => 'N°',
            'batch' => 'Batch',
            'compte' => 'Compte',
            'sens' => 'Sens',
            'montant' => 'Montant',
            'operation' => 'Opération',
            'date_valeur' => 'Date valeur',
            'agence' => 'Agence',
            'libelle' => 'Libellé',
            'utilisateur' => 'Utilisateur',
            'annee' => 'Année',
            'mois' => 'Mois',
        ];
    }

    /**
     * Transforme les lignes techniques en objet aligné sur le tableau « Template comptable » (écran Intégration RH).
     *
     * @param  array<int, array<string, mixed>>  $lignesPayload
     * @return array<int, array<string, mixed>>
     */
    private function lignesVersTemplateComptablePourIntegrationExterne(array $lignesPayload): array
    {
        $rows = [];
        foreach ($lignesPayload as $l) {
            $sens = (string) ($l['sens'] ?? '');
            $sensLibelle = match ($sens) {
                'credite' => 'Crédit',
                'debute' => 'Débit',
                default => $sens,
            };
            $montant = round((float) ($l['montant'] ?? 0), 2);
            $montantAfficheFr = number_format($montant, 2, ',', ' ').' FCFA';

            $dateIso = isset($l['date_de_valeur']) && $l['date_de_valeur'] !== null && $l['date_de_valeur'] !== ''
                ? (string) $l['date_de_valeur']
                : null;
            $dateLibelle = null;
            if ($dateIso !== null) {
                try {
                    $dateLibelle = Carbon::parse($dateIso)->timezone(config('app.timezone'))->format('d/m/Y');
                } catch (\Throwable) {
                    $dateLibelle = $dateIso;
                }
            }

            $mois = isset($l['mois_compte']) && $l['mois_compte'] !== null ? (int) $l['mois_compte'] : null;
            $moisFormate = $mois !== null ? str_pad((string) $mois, 2, '0', STR_PAD_LEFT) : null;

            $op = $l['code_operation'] ?? null;
            $opStr = $op !== null && trim((string) $op) !== '' ? (string) $op : null;

            $demandeId = isset($l['avance_salaire_demande_id']) ? (int) $l['avance_salaire_demande_id'] : null;

            $rows[] = [
                'avance_salaire_demande_id' => $demandeId,
                'numero' => isset($l['numero']) ? (int) $l['numero'] : null,
                'batch' => isset($l['no_batch']) ? (string) $l['no_batch'] : null,
                'compte' => isset($l['no_compte']) ? (string) $l['no_compte'] : null,
                'sens' => $sens,
                'sens_libelle' => $sensLibelle,
                'montant' => $montant,
                'montant_affiche_fcfa' => $montantAfficheFr,
                'operation' => $opStr,
                'date_valeur' => $dateIso,
                'date_valeur_libelle' => $dateLibelle,
                'agence' => isset($l['code_agence']) && $l['code_agence'] !== null && trim((string) $l['code_agence']) !== ''
                    ? (string) $l['code_agence']
                    : null,
                'libelle' => isset($l['libelle_ecriture']) ? (string) $l['libelle_ecriture'] : null,
                'utilisateur' => isset($l['user_id']) ? (int) $l['user_id'] : null,
                'annee' => isset($l['annee_compte']) ? (int) $l['annee_compte'] : null,
                'mois' => $mois,
                'mois_formate' => $moisFormate,
            ];
        }

        return $rows;
    }

    private function isNoBatchValide(?string $noBatch): bool
    {
        return is_string($noBatch) && preg_match('/^[A-Za-z0-9]{4}$/', $noBatch) === 1;
    }

    private function genererNoBatch(): string
    {
        do {
            $noBatch = Str::upper(Str::random(4));
        } while (
            DB::table('avance_salaire_integrations')->where('no_batch', $noBatch)->exists()
            || DB::table('avance_salaire_integration_lignes')->where('no_batch', $noBatch)->exists()
        );

        return $noBatch;
    }

    private function categorieStaffFromProfilPrioritaire(Profil $profil, string $requestCategorie): string
    {
        $rh = $profil->statut_rh;
        if ($rh !== null && trim((string) $rh) !== '') {
            return $this->mapStatutRhToCategorie($rh);
        }

        return $requestCategorie;
    }

    /**
     * @return array<int, string>
     */
    private function modesRemboursementPourType(string $typeAvance): array
    {
        $typesConfig = config('avance_salaire.types', []);
        $typeCfg = $typesConfig[$typeAvance] ?? [];
        $modes = $typeCfg['modes_remboursement'] ?? ['par_mois'];
        $modes = array_values(array_filter($modes, fn ($m) => in_array($m, ['par_mois', 'par_tranche'], true)));

        return $modes !== [] ? $modes : ['par_mois'];
    }

    private function modeRemboursementDefautPourType(string $typeAvance, ?array $modes = null): string
    {
        $typesConfig = config('avance_salaire.types', []);
        $typeCfg = $typesConfig[$typeAvance] ?? [];
        $modeDefaut = $typeCfg['mode_remboursement_defaut'] ?? null;
        $modesAutorises = $modes ?? $this->modesRemboursementPourType($typeAvance);

        if (is_string($modeDefaut) && in_array($modeDefaut, $modesAutorises, true)) {
            return $modeDefaut;
        }

        return $modesAutorises[0] ?? 'par_mois';
    }

    private function resolveModePaiementPourType(string $typeAvance, ?string $modeDemande): string
    {
        $modesAutorises = $this->modesRemboursementPourType($typeAvance);
        if ($modeDemande !== null && in_array($modeDemande, $modesAutorises, true)) {
            return $modeDemande;
        }

        return $this->modeRemboursementDefautPourType($typeAvance, $modesAutorises);
    }

    /**
     * Dates de prélèvement choisies par le staff (mode par tranche), triées et uniques.
     *
     * @param  array<int, mixed>|null  $datesInput
     * @return array<int, string>|null
     */
    private function normalizeDatesTranches(string $modePaiement, ?array $datesInput): ?array
    {
        if ($modePaiement !== 'par_tranche') {
            return null;
        }

        $raw = $datesInput ?? [];
        $out = [];

        foreach ($raw as $d) {
            if ($d === null || $d === '') {
                continue;
            }
            try {
                $out[] = Carbon::parse($d)->format('Y-m-d');
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'dates_tranches' => 'Une ou plusieurs dates de tranche ne sont pas valides.',
                ]);
            }
        }

        $out = array_values(array_unique($out));
        sort($out);

        if ($out === []) {
            throw ValidationException::withMessages([
                'dates_tranches' => 'Indiquez au moins une date de tranche pour le mode de paiement par tranche.',
            ]);
        }

        if (count($out) > 36) {
            throw ValidationException::withMessages([
                'dates_tranches' => 'Vous ne pouvez pas dépasser 36 dates de tranche.',
            ]);
        }

        return $out;
    }

    private function toDetail(AvanceSalaireDemande $d): array
    {
        return [
            'id' => $d->id,
            'created_at' => $d->created_at?->toIso8601String(),
            'matricule' => $d->matricule,
            'nom' => $d->nom,
            'prenom' => $d->prenom,
            'type_avance' => $d->type_avance ?? 'salaire',
            'mode_paiement' => $d->mode_paiement ?? $this->modeRemboursementDefautPourType($d->type_avance ?? 'salaire'),
            'dates_tranches' => $d->dates_tranches ?? [],
            'categorie_staff' => $d->categorie_staff ?? 'non_cadre',
            'compte_staff' => $d->compte_staff,
            'nombre_avance_en_cours' => (int) ($d->nombre_avance_en_cours ?? 0),
            'montant' => (float) $d->montant,
            'duree_mois' => $d->duree_mois,
            'date_premiere_echeance' => $d->date_premiere_echeance?->format('Y-m-d'),
            'salaire_net' => (float) $d->salaire_net,
            'salaire_domicilie' => $d->salaire_domicilie,
            'taux_interet_annuel_pct' => (float) $d->taux_interet_annuel_pct,
            'plafond_pct_applique' => (float) $d->plafond_pct_applique,
            'montant_max_autorise' => (float) $d->montant_max_autorise,
            'eligible' => $d->eligible,
            'eligibilite_messages' => $d->eligibilite_messages ?? [],
            'mensualite' => $d->mensualite !== null ? (float) $d->mensualite : null,
            'date_fin_prevue' => $d->date_fin_prevue?->format('Y-m-d'),
            'tableau_amortissement' => $d->tableau_amortissement ?? [],
            'statut' => $d->statut,
            'statut_label' => $d->libelleStatutWorkflow(),
            'statut_avant_attente' => $d->statut_avant_attente,
            'rh_decided_at' => $d->rh_decided_at?->toIso8601String(),
            'rh_decided_by' => $d->rhDecidedBy ? ['name' => $d->rhDecidedBy->name] : null,
            'rh_commentaire' => $d->rh_commentaire,
            'rh_niveau_finance' => $d->rh_niveau_finance,
            'cfo_validated_at' => $d->cfo_validated_at?->toIso8601String(),
            'cfo_validated_by' => $d->cfoValidatedBy ? ['name' => $d->cfoValidatedBy->name] : null,
            'cfo_commentaire' => $d->cfo_commentaire,
            'md_validated_at' => $d->md_validated_at?->toIso8601String(),
            'md_validated_by' => $d->mdValidatedBy ? ['name' => $d->mdValidatedBy->name] : null,
            'md_commentaire' => $d->md_commentaire,
            'finance_etape_libelle' => $this->libelleEtapeFinance($d),
            'finance_decided_at' => $d->finance_decided_at?->toIso8601String(),
            'finance_decided_by' => $d->financeDecidedBy ? ['name' => $d->financeDecidedBy->name] : null,
            'finance_commentaire' => $d->finance_commentaire,
            'rh_prise_en_charge_at' => $d->rh_prise_en_charge_at?->toIso8601String(),
            'rh_prise_en_charge_by' => $d->rhPriseEnChargeBy ? ['name' => $d->rhPriseEnChargeBy->name] : null,
            'rh_traitement_termine_at' => $d->rh_traitement_termine_at?->toIso8601String(),
            'rh_traitement_termine_by' => $d->rhTraitementTermineBy ? ['name' => $d->rhTraitementTermineBy->name] : null,
            'signature_employe' => $d->signature_employe,
            'signature_employe_at' => $d->signature_employe_at?->toIso8601String(),
            'signature_employe_by' => $d->signatureEmployeBy ? ['name' => $d->signatureEmployeBy->name] : null,
            'signature_rh' => $d->signature_rh,
            'signature_rh_at' => $d->signature_rh_at?->toIso8601String(),
            'signature_rh_by' => $d->signatureRhBy ? ['name' => $d->signatureRhBy->name] : null,
            'signature_finance' => $d->signature_finance,
            'signature_finance_at' => $d->signature_finance_at?->toIso8601String(),
            'signature_finance_by' => $d->signatureFinanceBy ? ['name' => $d->signatureFinanceBy->name] : null,
        ];
    }
}
