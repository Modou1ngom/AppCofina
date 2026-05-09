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
        $query = SigStaff::query()->orderByDesc('updated_at');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%");
            });
        }

        $staff = $query->paginate($perPage)->withQueryString();

        return Inertia::render('suivi-signature/staff/Index', [
            'staff' => $staff,
            'filters' => [
                'search' => $request->get('search', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('suivi-signature/staff/Create', [
            'siData' => null,
            'lookupDone' => false,
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

        $staff = SigStaff::create($validated);
        $staff->synchroniserEncoursTotaux();

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
            'type_relation' => 'required|string|max:255',
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
                        config('sig.encours_taux_seuil_pct', 10)
                    ),
                ])
                ->with('error', 'Seuil d’encours dépassé. Corrigez la situation (fonds propres, encours ou liens) avant d’ajouter une personne liée.');
        }

        $personne = SigPersonneLiee::query()->findOrFail($validated['sig_personne_liee_id']);
        $staff->refresh();
        $sumLieesActuel = (float) $staff->personnesLiees()->sum('encours_credit');
        $totalProjete = (float) $staff->encours_staff_si + $sumLieesActuel + (float) $personne->encours_credit;
        $fp = (float) ($staff->fonds_propres ?? 0);
        $seuil = (float) config('sig.encours_taux_seuil_pct', 10);
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
