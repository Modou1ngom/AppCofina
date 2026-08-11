<?php

namespace App\Http\Controllers;

use App\Models\Profil;
use App\Models\SigPersonneLiee;
use App\Models\SigStaff;
use App\Models\SigStaffEncoursConformiteEvent;
use App\Services\SigSiLookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SigStaffController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,conformite')->only([
            'index', 'create', 'store', 'createManuel', 'storeManuel', 'edit', 'update', 'destroy',
        ]);
    }

    /**
     * Espace collaborateur : déclaration des personnes apparentées ou liées sur sa propre fiche.
     */
    public function mesPersonnesLiees(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user->isAdmin() || $user->isConformite()) {
            return redirect()->route('suivi-signature.staff.index');
        }

        if (! $user->profil) {
            return Inertia::render('suivi-signature/staff/MesPersonnesLiees', [
                'staff' => null,
                'missingProfil' => true,
                'missingFiche' => false,
                'profilMatricule' => null,
                'requiresSynchronisationClientSi' => false,
            ]);
        }

        $staff = $user->sigStaffFiche();
        if (! $staff) {
            return Inertia::render('suivi-signature/staff/MesPersonnesLiees', [
                'staff' => null,
                'missingProfil' => false,
                'missingFiche' => true,
                'profilMatricule' => $user->profil->matricule,
                'requiresSynchronisationClientSi' => false,
            ]);
        }

        $staff->load(['personnesLiees']);
        $staff->synchroniserEncoursTotaux();
        $staff->refresh();

        return Inertia::render('suivi-signature/staff/MesPersonnesLiees', [
            'staff' => $staff,
            'sigMetriquesEncours' => $staff->metriquesEncoursPourVue(),
            'missingProfil' => false,
            'missingFiche' => false,
            'profilMatricule' => null,
            'requiresSynchronisationClientSi' => $staff->doitSynchroniserClientSiAvantLiens(),
        ]);
    }

    /**
     * Création de la fiche staff par le collaborateur : matricule = celui du profil RH, données complétées depuis le SI.
     */
    public function initialiserMaFiche(Request $request, SigSiLookupService $siLookup): RedirectResponse
    {
        $user = $request->user();
        if (! $user || $user->isAdmin() || $user->isConformite()) {
            abort(403);
        }

        $user->loadMissing('profil');
        if (! $user->profil) {
            return redirect()->route('suivi-signature.staff.mes-personnes-liees')
                ->with('error', 'Aucun profil RH n’est associé à votre compte.');
        }

        if ($user->sigStaffFiche()) {
            return redirect()->route('suivi-signature.staff.mes-personnes-liees')
                ->with('success', 'Votre fiche suivi signature existe déjà.');
        }

        $validated = $request->validate([
            'matricule' => 'required|string|max:100',
        ]);

        $saisie = trim($validated['matricule']);
        $attendu = trim((string) $user->profil->matricule);
        if ($saisie === '' || $attendu === '' || ! hash_equals($attendu, $saisie)) {
            return back()->withErrors([
                'matricule' => 'Saisissez exactement le matricule de votre profil RH.',
            ]);
        }

        $siData = $siLookup->lookup($saisie, 'personnel');
        if ($siData === null) {
            return back()->withErrors([
                'matricule' => 'Aucune donnée trouvée dans le SI pour ce matricule. Vérifiez la saisie ou contactez la conformité.',
            ]);
        }

        if (($siData['type_client'] ?? 'personnel') !== 'personnel') {
            return back()->withErrors([
                'matricule' => 'Ce numéro ne correspond pas à un dossier personnel dans le SI.',
            ]);
        }

        if (array_key_exists('profile_id', $siData) && $siData['profile_id'] !== null && $siData['profile_id'] !== '') {
            if ((int) $siData['profile_id'] !== (int) $user->profil->id) {
                return back()->withErrors([
                    'matricule' => 'Les données SI ne correspondent pas à votre profil RH.',
                ]);
            }
        }

        $reference = (string) ($siData['matricule'] ?? $saisie);
        $existant = SigStaff::query()->where('reference', $reference)->first();
        if ($existant) {
            if ((int) $existant->profile_id !== (int) $user->profil->id) {
                return back()->withErrors([
                    'matricule' => 'Ce matricule est déjà enregistré sur une autre fiche staff. Contactez la conformité.',
                ]);
            }

            if ($existant->doitSynchroniserClientSiAvantLiens()) {
                return redirect()->route('suivi-signature.staff.mes-personnes-liees')
                    ->with('error', 'Complétez d’abord votre numéro client SI (KYC et encours propre) sur cette page avant de lier des personnes.');
            }

            return redirect()->route('suivi-signature.staff.mes-personnes-liees')
                ->with('success', 'Votre fiche est prête. Vous pouvez déclarer vos personnes liées.');
        }

        $staff = SigStaff::create($this->staffPayloadInitialeCollaborateur($siData, $user->profil));
        $staff->synchroniserEncoursTotaux();

        return redirect()->route('suivi-signature.staff.mes-personnes-liees')
            ->with('success', 'Fiche créée à partir de votre matricule RH. Saisissez maintenant votre numéro client SI pour charger le KYC et votre encours propre, puis vous pourrez lier vos personnes apparentées ou liées.');
    }

    /**
     * Étape 2 collaborateur : numéro client dans le SI → KYC + encours propre (`encours_staff_si`) sur la fiche locale.
     */
    public function synchroniserMaFicheClientSi(Request $request, SigSiLookupService $siLookup): RedirectResponse
    {
        $user = $request->user();
        if (! $user || $user->isAdmin() || $user->isConformite()) {
            abort(403);
        }

        $user->loadMissing('profil');
        if (! $user->profil) {
            return redirect()->route('suivi-signature.staff.mes-personnes-liees')
                ->with('error', 'Aucun profil RH n’est associé à votre compte.');
        }

        $staff = $user->sigStaffFiche();
        if (! $staff || $staff->type_personne !== 'staff') {
            return redirect()->route('suivi-signature.staff.mes-personnes-liees')
                ->with('error', 'Aucune fiche staff à mettre à jour.');
        }

        $validated = $request->validate([
            'matricule_client' => 'required|string|max:100',
        ]);

        $saisie = trim($validated['matricule_client']);
        if ($saisie === '') {
            return back()->withErrors([
                'matricule_client' => 'Saisissez votre numéro client SI.',
            ]);
        }

        $siData = $siLookup->lookup($saisie, 'personnel');
        if ($siData === null) {
            return back()->withErrors([
                'matricule_client' => 'Aucune donnée trouvée dans le SI pour ce numéro client. Vérifiez la saisie ou contactez la conformité.',
            ]);
        }

        if (($siData['type_client'] ?? 'personnel') !== 'personnel') {
            return back()->withErrors([
                'matricule_client' => 'Ce numéro ne correspond pas à un dossier personnel dans le SI.',
            ]);
        }

        if (array_key_exists('profile_id', $siData) && $siData['profile_id'] !== null && $siData['profile_id'] !== '') {
            if ((int) $siData['profile_id'] !== (int) $user->profil->id) {
                return back()->withErrors([
                    'matricule_client' => 'Les données SI ne correspondent pas à votre profil RH.',
                ]);
            }
        }

        $staff->fill($this->champsKycEncoursNumeroClientDepuisSiPersonnel($siData));
        $staff->save();
        $staff->synchroniserEncoursTotaux();

        return redirect()->back()
            ->with('success', 'KYC et encours propre mis à jour à partir du SI. Vous pouvez maintenant déclarer vos personnes liées.');
    }

    /**
     * Fiche créée au matricule RH : identité / poste depuis le SI, sans KYC ni encours (étape client SI séparée).
     *
     * @param  array<string, mixed>  $siData
     * @return array<string, mixed>
     */
    private function staffPayloadInitialeCollaborateur(array $siData, Profil $profil): array
    {
        $isEntreprise = ($siData['type_client'] ?? 'personnel') === 'entreprise';
        $prenom = $isEntreprise ? '—' : (string) ($siData['prenom'] ?? '');
        $nom = $isEntreprise
            ? (string) ($siData['raison_sociale'] ?? $siData['prenom_nom'] ?? '')
            : (string) ($siData['nom'] ?? '');

        return [
            'reference' => (string) ($siData['matricule'] ?? $profil->matricule),
            'numero_client_si' => null,
            'profile_id' => $profil->id,
            'prenom' => $prenom,
            'nom' => $nom,
            'fonction' => $siData['fonction'] ?? null,
            'departement' => $siData['departement'] ?? null,
            'type_personne' => 'staff',
            'statut' => 'actif',
            'kyc_piece_identite' => null,
            'kyc_adresse' => null,
            'kyc_telephone' => null,
            'encours_staff_si' => 0,
            'encours_credit_individuel' => 0,
            'score_risque' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $siData
     * @return array<string, mixed>
     */
    private function champsKycEncoursNumeroClientDepuisSiPersonnel(array $siData): array
    {
        $pieceType = (string) ($siData['piece_type'] ?? 'CNI');
        $pieceNum = $siData['piece_numero'] ?? null;
        $kycPiece = ($pieceNum !== null && trim((string) $pieceNum) !== '')
            ? $pieceType.' — '.$pieceNum
            : $pieceType;

        $encStaff = SigPersonneLiee::encoursFromSiPayload($siData) ?? 0.0;

        return [
            'numero_client_si' => trim((string) ($siData['matricule'] ?? '')),
            'kyc_piece_identite' => $kycPiece,
            'kyc_adresse' => $siData['adresse'] ?? null,
            'kyc_telephone' => $siData['telephone'] ?? null,
            'encours_staff_si' => $encStaff,
        ];
    }

    public function index(Request $request): Response
    {
        $perPage = (int) $request->get('per_page', 10);
        $params = \App\Models\SigParametre::current();
        $seuil = $params->seuilTauxPct();
        $fpGlobal = $params->fondsPropresReference();
        $query = SigStaff::query()
            ->withSum('personnesLiees as encours_personnes_liees_sum', 'encours_credit')
            ->orderByDesc('updated_at');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('numero_client_si', 'like', "%{$search}%")
                    ->orWhere('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('fonction', 'like', "%{$search}%");
            });
        }

        $allForSynthese = (clone $query)->get(['id', 'encours_staff_si', 'fonds_propres']);
        $encoursStaffTotal = 0.0;
        $encoursLieesTotal = 0.0;
        $nbConforme = 0;
        $nbAlerte = 0;
        $nbDepassement = 0;
        $nbNonEvalue = 0;

        foreach ($allForSynthese as $s) {
            $encoursStaff = (float) $s->encours_staff_si;
            $encoursLiees = (float) ($s->encours_personnes_liees_sum ?? 0);
            $encoursStaffTotal += $encoursStaff;
            $encoursLieesTotal += $encoursLiees;
            $fp = $fpGlobal ?? $s->fondsPropresReference();
            $encoursTotal = $encoursStaff + $encoursLiees;
            $ratio = ($fp !== null && $fp > 0) ? round(($encoursTotal / $fp) * 100, 2) : null;
            $statut = $params->statutConformitePourRatio($ratio);
            match ($statut) {
                'conforme' => $nbConforme++,
                'alerte' => $nbAlerte++,
                'depassement' => $nbDepassement++,
                default => $nbNonEvalue++,
            };
        }

        $encoursConsolide = round($encoursStaffTotal + $encoursLieesTotal, 2);
        $fpRef = $fpGlobal;
        $ratioGlobal = ($fpRef !== null && $fpRef > 0)
            ? round(($encoursConsolide / $fpRef) * 100, 2)
            : null;
        $plafondGlobal = ($fpRef !== null && $fpRef > 0)
            ? round($fpRef * ($seuil / 100), 2)
            : null;
        $ecartGlobal = $plafondGlobal !== null
            ? round($plafondGlobal - $encoursConsolide, 2)
            : null;

        $synthese = [
            'nb_fiches' => $allForSynthese->count(),
            'fonds_propres_reference' => $fpRef,
            'seuil_pct' => $seuil,
            'plafond_reglementaire' => $plafondGlobal,
            'encours_staff_ca' => round($encoursStaffTotal, 2),
            'encours_personnes_liees' => round($encoursLieesTotal, 2),
            'encours_total' => $encoursConsolide,
            'ratio_pct' => $ratioGlobal,
            'ecart' => $ecartGlobal,
            'statut_conformite' => $params->statutConformitePourRatio($ratioGlobal),
            'nb_conforme' => $nbConforme,
            'nb_alerte' => $nbAlerte,
            'nb_depassement' => $nbDepassement,
            'nb_non_evalue' => $nbNonEvalue,
        ];

        $staff = $query->paginate($perPage)->withQueryString()->through(function (SigStaff $s) use ($params, $seuil, $fpGlobal) {
            $fp = $fpGlobal ?? $s->fondsPropresReference();
            $encoursStaff = (float) $s->encours_staff_si;
            $encoursLiees = (float) ($s->encours_personnes_liees_sum ?? 0);
            $encoursTotal = round($encoursStaff + $encoursLiees, 2);
            $plafond = ($fp !== null && $fp > 0) ? round($fp * ($seuil / 100), 2) : null;
            $ratio = ($fp !== null && $fp > 0) ? round(($encoursTotal / $fp) * 100, 2) : null;
            $ecart = $plafond !== null ? round($plafond - $encoursTotal, 2) : null;

            return [
                'id' => $s->id,
                'matricule' => trim((string) ($s->numero_client_si ?: $s->reference)),
                'nom_complet' => $s->nom_complet,
                'fonction' => $s->fonction,
                'fonds_propres' => $fp,
                'seuil_pct' => $seuil,
                'plafond_reglementaire' => $plafond,
                'encours_staff_ca' => $encoursStaff,
                'encours_personnes_liees' => $encoursLiees,
                'encours_total' => $encoursTotal,
                'ratio_pct' => $ratio,
                'ecart' => $ecart,
                'statut_conformite' => $params->statutConformitePourRatio($ratio),
                'type_personne' => $s->type_personne,
                'statut' => $s->statut,
            ];
        });

        return Inertia::render('suivi-signature/staff/Index', [
            'staff' => $staff,
            'synthese' => $synthese,
            'filters' => [
                'search' => $request->get('search', ''),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        if ($request->boolean('reset')) {
            $request->session()->forget(['sig_lookup_si_data', 'sig_lookup_personnes_liees_si', 'sig_lookup_context']);
        }

        $siData = $request->session()->get('sig_lookup_si_data');
        $personnesLieesSi = $request->session()->get('sig_lookup_personnes_liees_si', []);

        return Inertia::render('suivi-signature/staff/Create', [
            'siData' => $siData,
            'lookupDone' => $siData !== null,
            'personnesLieesSi' => is_array($personnesLieesSi) ? $personnesLieesSi : [],
        ]);
    }

    /**
     * Saisie manuelle réservée aux membres du Conseil d'administration absents du SI (conformité).
     */
    public function createManuel(): Response
    {
        return Inertia::render('suivi-signature/staff/CreateManuel');
    }

    public function storeManuel(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->isConformite())) {
            abort(403);
        }

        $validated = $request->validate([
            'reference' => 'required|string|max:100|unique:sig_staffs,reference',
            'prenom' => 'required|string|max:255',
            'nom' => 'required|string|max:255',
            'fonction' => 'nullable|string|max:255',
            'departement' => 'nullable|string|max:255',
            'statut' => 'required|in:actif,inactif',
            'kyc_piece_identite' => 'nullable|string|max:255',
            'kyc_adresse' => 'nullable|string',
            'kyc_telephone' => 'nullable|string|max:50',
            'encours_credit_individuel' => 'nullable|numeric|min:0',
            'encours_staff_si' => 'nullable|numeric|min:0',
            'fonds_propres' => 'nullable|numeric|min:0',
            'score_risque' => 'nullable|numeric|min:0',
        ]);

        $validated['profile_id'] = null;
        $validated['type_personne'] = 'administrateur';
        $validated['encours_staff_si'] = $validated['encours_staff_si'] ?? $validated['encours_credit_individuel'] ?? 0;
        unset($validated['encours_credit_individuel']);
        $validated['encours_credit_individuel'] = 0;

        $staff = SigStaff::create($validated);
        $staff->synchroniserEncoursTotaux();

        return redirect()->route('suivi-signature.staff.index')
            ->with('success', 'Membre du Conseil d\'administration enregistré (saisie conformité).');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'si_confirmed' => 'required|accepted',
            'reference' => 'required|string|max:100|unique:sig_staffs,reference',
            'numero_client_si' => 'nullable|string|max:100',
            'profile_id' => 'nullable|exists:profiles,id',
            'prenom' => 'required|string|max:255',
            'nom' => 'required|string|max:255',
            'fonction' => 'nullable|string|max:255',
            'departement' => 'nullable|string|max:255',
            'type_personne' => 'required|in:staff,administrateur,apparente_ou_liee',
            'statut' => 'required|in:actif,inactif',
            'kyc_piece_identite' => 'nullable|string|max:255',
            'kyc_adresse' => 'nullable|string',
            'kyc_telephone' => 'nullable|string|max:50',
            'encours_credit_individuel' => 'nullable|numeric|min:0',
            'encours_staff_si' => 'nullable|numeric|min:0',
            'fonds_propres' => 'nullable|numeric|min:0',
            'score_risque' => 'nullable|numeric|min:0',
        ]);

        $validated['encours_staff_si'] = $validated['encours_staff_si'] ?? $validated['encours_credit_individuel'] ?? 0;
        unset($validated['encours_credit_individuel'], $validated['si_confirmed']);
        $validated['encours_credit_individuel'] = 0;
        // N° client SI = CUSTOMER_NO (pas le n° de pièce)
        $validated['numero_client_si'] = trim((string) ($validated['numero_client_si'] ?? $validated['reference'] ?? '')) ?: null;

        $staff = SigStaff::create($validated);
        $staff->synchroniserEncoursTotaux();

        $request->session()->forget([
            'sig_lookup_si_data',
            'sig_lookup_personnes_liees_si',
            'sig_lookup_context',
            'sig_attach_staff_id',
        ]);

        return redirect()->route('suivi-signature.staff.index')
            ->with('success', 'Fiche staff enregistrée.');
    }

    public function show(Request $request, SigStaff $staff): Response
    {
        $this->authorizeStaffAccess($request, $staff);

        $staff->load(['profile', 'personnesLiees']);
        $staff->synchroniserEncoursTotaux();
        $staff->refresh();

        $personnesDisponibles = SigPersonneLiee::query()
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'prenom', 'nom', 'raison_sociale', 'est_personne_morale']);

        $user = $request->user();
        $requiresSynchronisationClientSi = $staff->doitSynchroniserClientSiAvantLiens()
            && $user
            && ! $user->isAdmin()
            && ! $user->isConformite()
            && $user->profil
            && (int) $staff->profile_id === (int) $user->profil->id;

        $conformiteEncoursHistorique = [];
        $peutCommenterConformiteEncours = false;
        if ($user && ($user->isAdmin() || $user->isConformite())) {
            $conformiteEncoursHistorique = $staff->conformiteEncoursEvents()
                ->with('user:id,name,email')
                ->latest()
                ->limit(40)
                ->get()
                ->map(fn (SigStaffEncoursConformiteEvent $e) => [
                    'id' => $e->id,
                    'type' => $e->type,
                    'type_label' => SigStaffEncoursConformiteEvent::typeLabel($e->type),
                    'created_at' => $e->created_at?->format('d/m/Y H:i'),
                    'fonds_propres' => $e->fonds_propres,
                    'encours_consolide' => $e->encours_consolide,
                    'taux_pct' => $e->taux_pct,
                    'seuil_pct' => $e->seuil_pct,
                    'commentaire' => $e->commentaire,
                    'user' => $e->user ? ['name' => $e->user->name, 'email' => $e->user->email] : null,
                ])
                ->values()
                ->all();
            $peutCommenterConformiteEncours = true;
        }

        return Inertia::render('suivi-signature/staff/Show', [
            'staff' => $staff,
            'sigMetriquesEncours' => $staff->metriquesEncoursPourVue(),
            'personnesDisponibles' => $personnesDisponibles,
            'requiresSynchronisationClientSi' => $requiresSynchronisationClientSi,
            'conformiteEncoursHistorique' => $conformiteEncoursHistorique,
            'peutCommenterConformiteEncours' => $peutCommenterConformiteEncours,
        ]);
    }

    public function edit(SigStaff $staff): Response
    {
        $this->authorizeStaffAccess(request(), $staff);

        $profils = Profil::query()
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'matricule', 'prenom', 'nom', 'departement', 'email']);

        return Inertia::render('suivi-signature/staff/Edit', [
            'staff' => $staff,
            'profils' => $profils,
        ]);
    }

    public function update(Request $request, SigStaff $staff): RedirectResponse
    {
        $this->authorizeStaffAccess($request, $staff);

        $validated = $request->validate([
            'reference' => 'required|string|max:100|unique:sig_staffs,reference,'.$staff->id,
            'profile_id' => 'nullable|exists:profiles,id',
            'prenom' => 'required|string|max:255',
            'nom' => 'required|string|max:255',
            'fonction' => 'nullable|string|max:255',
            'departement' => 'nullable|string|max:255',
            'type_personne' => 'required|in:staff,administrateur,apparente_ou_liee',
            'statut' => 'required|in:actif,inactif',
            'kyc_piece_identite' => 'nullable|string|max:255',
            'kyc_adresse' => 'nullable|string',
            'kyc_telephone' => 'nullable|string|max:50',
            'encours_staff_si' => 'nullable|numeric|min:0',
            'fonds_propres' => 'nullable|numeric|min:0',
            'score_risque' => 'nullable|numeric|min:0',
        ]);

        $staff->update($validated);
        $staff->synchroniserEncoursTotaux();

        return redirect()->route('suivi-signature.staff.show', $staff)
            ->with('success', 'Fiche staff mise à jour.');
    }

    /**
     * Gérer les liaisons personnes ↔ ce staff signataire uniquement.
     */
    public function lierPersonnes(Request $request, SigStaff $staff, SigSiLookupService $siLookup): Response
    {
        $this->authorizeStaffAccess($request, $staff);

        $staff = $this->assurerNumeroClientSi($staff, $siLookup);

        $staff->load(['personnesLiees']);
        $staff->synchroniserEncoursTotaux();
        $staff->refresh();

        $idsLies = $staff->personnesLiees->pluck('id');
        $numerosLies = $staff->personnesLiees
            ->pluck('numero_client')
            ->filter()
            ->map(fn ($n) => trim((string) $n))
            ->all();

        $personnesDisponibles = SigPersonneLiee::query()
            ->whereNotIn('id', $idsLies)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'prenom', 'nom', 'raison_sociale', 'est_personne_morale', 'numero_client', 'encours_credit']);

        $user = $request->user();
        $peutCreer = $user && ($user->isAdmin() || $user->isConformite());

        $suggestionsSi = $this->suggestionsClientsLiesSi($staff, $siLookup, $numerosLies);

        return Inertia::render('suivi-signature/staff/LierPersonnes', [
            'staff' => $staff,
            'sigMetriquesEncours' => $staff->metriquesEncoursPourVue(),
            'personnesDisponibles' => $personnesDisponibles,
            'peutCreerFichePersonneLiee' => $peutCreer,
            'peutResoudreSi' => $peutCreer || ($user && $user->peutDeclarerPersonnesLieesSig()),
            'suggestionsSi' => $suggestionsSi,
            'cleDetectionSi' => $this->cleSiPourDetectionClients($staff),
        ]);
    }

    /**
     * Détection auto (caution + cotitulaires) puis association des clients trouvés à ce staff.
     */
    public function detecterEtLierClientsSi(Request $request, SigStaff $staff, SigSiLookupService $siLookup): RedirectResponse
    {
        $this->authorizeStaffAccess($request, $staff);

        $staff = $this->assurerNumeroClientSi($staff, $siLookup);

        $cle = $this->cleSiPourDetectionClients($staff);
        if ($cle === null) {
            return back()->with('error', 'Impossible de détecter : renseignez le n° client SI du staff (CUSTOMER_NO), pas le n° de pièce d’identité.');
        }

        if ($staff->liaisonPersonnesLieesBloquee()) {
            return back()->with('error', 'Seuil d’encours dépassé : nouvelles liaisons bloquées pour ce signataire.');
        }

        $rows = $siLookup->personnesLieesSiPourMatricule($cle);
        if ($rows === []) {
            return back()->with('error', 'Aucun client lié détecté dans le SI pour ce staff (caution / cotitulaire).');
        }

        $staff->load('personnesLiees');
        $numerosLies = $staff->personnesLiees
            ->pluck('numero_client')
            ->filter()
            ->map(fn ($n) => trim((string) $n))
            ->all();

        $lies = 0;
        $ignores = 0;
        $erreurs = 0;

        foreach ($rows as $row) {
            $numero = trim((string) ($row['numero_client'] ?? $row['matricule'] ?? ''));
            if ($numero === '' || in_array($numero, $numerosLies, true)) {
                $ignores++;

                continue;
            }

            $typeRelation = trim((string) ($row['type_relation'] ?? 'Lié SI'));
            if ($typeRelation === '') {
                $typeRelation = 'Lié SI';
            }
            $classe = max(1, min(4, (int) ($row['classe'] ?? 2)));

            try {
                $siData = $siLookup->lookup($numero, ! empty($row['est_personne_morale']) ? 'entreprise' : 'personnel');
                if ($siData === null) {
                    // Fallback minimal depuis la ligne de détection
                    $siData = [
                        'matricule' => $numero,
                        'type_client' => ! empty($row['est_personne_morale']) ? 'entreprise' : 'personnel',
                        'prenom' => $row['prenom'] ?? null,
                        'nom' => $row['nom'] ?? null,
                        'raison_sociale' => $row['raison_sociale'] ?? null,
                        'prenom_nom' => $row['prenom_nom'] ?? $numero,
                        'adresse' => null,
                        'telephone' => $row['telephone'] ?? null,
                        'email' => null,
                        'piece_type' => $row['piece_type'] ?? 'CNI',
                        'piece_numero' => $row['piece_numero'] ?? null,
                    ];
                }

                $personne = SigPersonneLiee::ensureFromSiData($siData);

                if ($staff->personnesLiees()->whereKey($personne->id)->exists()) {
                    $ignores++;
                    $numerosLies[] = $numero;

                    continue;
                }

                // Contrôle seuil projeté
                $staff->refresh();
                $sumLiees = (float) $staff->personnesLiees()->sum('encours_credit');
                $totalProjete = (float) $staff->encours_staff_si + $sumLiees + (float) $personne->encours_credit;
                $fp = (float) ($staff->fondsPropresReference() ?? 0);
                $seuil = \App\Models\SigParametre::current()->seuilTauxPct();
                if ($fp > 0 && ($totalProjete / $fp) * 100 > $seuil) {
                    $erreurs++;

                    continue;
                }

                $staff->personnesLiees()->attach($personne->id, [
                    'type_relation' => $typeRelation,
                    'classe' => $classe,
                ]);
                $numerosLies[] = $numero;
                $lies++;
            } catch (\Throwable) {
                $erreurs++;
            }
        }

        $staff->synchroniserEncoursTotaux();

        if ($lies === 0 && $erreurs === 0) {
            return back()->with('success', 'Détection terminée : aucun nouveau client à lier (déjà associés ou liste vide).');
        }

        $msg = sprintf('Détection automatique : %d client(s) lié(s) à ce signataire.', $lies);
        if ($ignores > 0) {
            $msg .= sprintf(' %d ignoré(s).', $ignores);
        }
        if ($erreurs > 0) {
            $msg .= sprintf(' %d en échec (seuil ou SI).', $erreurs);
        }

        return back()->with($erreurs > 0 && $lies === 0 ? 'error' : 'success', $msg);
    }

    /**
     * @param  list<string>  $numerosLies
     * @return list<array<string, mixed>>
     */
    private function suggestionsClientsLiesSi(SigStaff $staff, SigSiLookupService $siLookup, array $numerosLies): array
    {
        $cle = $this->cleSiPourDetectionClients($staff);
        if ($cle === null) {
            return [];
        }

        $rows = $siLookup->personnesLieesSiPourMatricule($cle);
        $out = [];
        foreach ($rows as $row) {
            $numero = trim((string) ($row['numero_client'] ?? $row['matricule'] ?? ''));
            if ($numero === '' || in_array($numero, $numerosLies, true)) {
                continue;
            }
            $out[] = [
                'numero_client' => $numero,
                'prenom_nom' => $row['prenom_nom'] ?? $numero,
                'prenom' => $row['prenom'] ?? null,
                'nom' => $row['nom'] ?? null,
                'raison_sociale' => $row['raison_sociale'] ?? null,
                'est_personne_morale' => (bool) ($row['est_personne_morale'] ?? false),
                'type_relation' => $row['type_relation'] ?? 'Lié SI',
                'classe' => (int) ($row['classe'] ?? 2),
                'detail_relation' => $row['detail_relation'] ?? null,
                'kyc_staff' => $row['kyc_staff'] ?? null,
                'kyc_staff_piece' => $row['kyc_staff_piece'] ?? null,
                'piece_type' => $row['piece_type'] ?? null,
                'piece_numero' => $row['piece_numero'] ?? null,
            ];
        }

        return array_values($out);
    }

    private function cleSiPourDetectionClients(SigStaff $staff): ?string
    {
        // Priorité : n° client SI (CUSTOMER_NO), jamais le n° de pièce d'identité
        $client = trim((string) ($staff->numero_client_si ?? ''));
        if ($client !== '') {
            return $client;
        }

        // La référence réglementaire est souvent le CUSTOMER_NO FCUBS
        $ref = trim((string) ($staff->reference ?? ''));

        return $ref !== '' ? $ref : null;
    }

    /**
     * Garantit numero_client_si (CUSTOMER_NO) pour la détection — pas le n° de pièce KYC.
     */
    private function assurerNumeroClientSi(SigStaff $staff, SigSiLookupService $siLookup): SigStaff
    {
        if (trim((string) ($staff->numero_client_si ?? '')) !== '') {
            return $staff;
        }

        $candidats = array_values(array_filter([
            trim((string) ($staff->reference ?? '')),
        ]));

        $piece = trim((string) ($staff->kyc_piece_identite ?? ''));
        if ($piece !== '' && preg_match('/(\d{6,})/', $piece, $m)) {
            $candidats[] = $m[1];
        }

        foreach (array_unique($candidats) as $cle) {
            if ($cle === '') {
                continue;
            }
            $si = $siLookup->lookup($cle, 'personnel');
            if ($si === null) {
                continue;
            }
            $customerNo = trim((string) ($si['matricule'] ?? ''));
            if ($customerNo === '') {
                continue;
            }
            $staff->forceFill(['numero_client_si' => $customerNo])->saveQuietly();

            return $staff->refresh();
        }

        return $staff;
    }

    public function destroy(SigStaff $staff): RedirectResponse
    {
        $this->authorizeStaffAccess(request(), $staff);

        $staff->delete();

        return redirect()->route('suivi-signature.staff.index')
            ->with('success', 'Fiche staff supprimée.');
    }

    public function attachPersonne(Request $request, SigStaff $staff): RedirectResponse
    {
        $this->authorizeStaffAccess($request, $staff);

        $validated = $request->validate([
            'sig_personne_liee_id' => 'required|exists:sig_personnes_liees,id',
            'type_relation' => ['required', 'string', 'max:255', \App\Support\SigTypeRelation::rule()],
            'classe' => 'required|integer|min:1|max:4',
        ]);

        $user = $request->user();
        if ($staff->doitSynchroniserClientSiAvantLiens() && $user && ! $user->isAdmin() && ! $user->isConformite()) {
            return back()
                ->withErrors([
                    'sig_personne_liee_id' => 'Renseignez d’abord votre numéro client SI (KYC et encours propre) sur « Mes personnes liées ».',
                ])
                ->with('error', 'Étape obligatoire : synchronisation du numéro client SI avant toute nouvelle liaison.');
        }

        if ($staff->personnesLiees()->where('sig_personne_liee_id', $validated['sig_personne_liee_id'])->exists()) {
            return back()->withErrors(['sig_personne_liee_id' => 'Cette personne est déjà liée à ce staff.']);
        }

        $staff->refresh();
        $staff->synchroniserEncoursTotaux();
        $staff->refresh();

        if ($staff->liaisonPersonnesLieesBloquee()) {
            return back()
                ->withErrors([
                    'sig_personne_liee_id' => sprintf(
                        'Nouvelles liaisons interdites : le taux encours / fonds propres (%.2f %%) dépasse déjà le seuil de %s %%.',
                        $staff->tauxEncoursFondsPropres() ?? 0.0,
                        \App\Models\SigParametre::current()->seuilTauxPct()
                    ),
                ])
                ->with('error', 'Seuil d’encours dépassé. Corrigez la situation (fonds propres, encours ou liens) avant d’ajouter une personne liée.');
        }

        $personne = SigPersonneLiee::query()->findOrFail($validated['sig_personne_liee_id']);
        $staff->refresh();
        $sumLieesActuel = (float) $staff->personnesLiees()->sum('encours_credit');
        $totalProjete = (float) $staff->encours_staff_si + $sumLieesActuel + (float) $personne->encours_credit;
        $fp = (float) ($staff->fondsPropresReference() ?? 0);
        $seuil = \App\Models\SigParametre::current()->seuilTauxPct();
        if ($fp > 0 && ($totalProjete / $fp) * 100 > $seuil) {
            return back()
                ->withErrors([
                    'sig_personne_liee_id' => sprintf(
                        'Liaison impossible : le taux encours / fonds propres dépasserait %s %% (projeté : %.2f %%).',
                        $seuil,
                        ($totalProjete / $fp) * 100
                    ),
                ])
                ->with('error', 'Seuil d’encours dépassé : aucune nouvelle liaison n’est autorisée tant que le ratio n’est pas revenu sous le seuil ou que les fonds propres de référence ne sont pas ajustés.');
        }

        $staff->personnesLiees()->attach($validated['sig_personne_liee_id'], [
            'type_relation' => $validated['type_relation'],
            'classe' => $validated['classe'],
        ]);

        $staff->synchroniserEncoursTotaux();

        return back()->with('success', 'Personne liée associée.');
    }

    public function detachPersonne(SigStaff $staff, SigPersonneLiee $personneLiee): RedirectResponse
    {
        $this->authorizeStaffAccess(request(), $staff);

        $staff->personnesLiees()->detach($personneLiee->id);

        $staff->synchroniserEncoursTotaux();

        return back()->with('success', 'Lien supprimé.');
    }

    private function authorizeStaffAccess(Request $request, SigStaff $staff): void
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }
        if ($user->isAdmin() || $user->isConformite()) {
            return;
        }
        $user->loadMissing('profil');
        if ($user->profil && (int) $staff->profile_id === (int) $user->profil->id) {
            return;
        }
        abort(403);
    }
}
