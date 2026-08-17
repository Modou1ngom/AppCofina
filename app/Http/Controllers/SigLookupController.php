<?php

namespace App\Http\Controllers;

use App\Models\SigPersonneLiee;
use App\Models\SigStaff;
use App\Services\SigSiLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SigLookupController extends Controller
{
    public function __construct(
        private readonly SigSiLookupService $siLookup
    ) {}

    /**
     * Refresh / accès direct en GET : renvoie vers le formulaire de création (évite 405).
     */
    public function lookupFormRedirect(Request $request): RedirectResponse
    {
        $context = $request->session()->pull('sig_lookup_context', 'personne_liee');

        return redirect()->route(match ($context) {
            'staff' => 'suivi-signature.staff.create',
            'membre_ca' => 'suivi-signature.staff.manuel.create',
            default => 'suivi-signature.personnes-liees.create',
        });
    }

    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'context' => 'required|in:staff,personne_liee,membre_ca',
            'matricule' => 'required|string|max:100',
            'type_client' => 'required|in:personnel,entreprise',
        ]);

        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if (in_array($validated['context'], ['staff', 'membre_ca'], true) && ! $user->isAdmin() && ! $user->isConformite()) {
            abort(403, 'Seuls les administrateurs ou la conformité peuvent créer une fiche depuis le SI.');
        }

        if ($validated['context'] === 'personne_liee' && ! $user->isAdmin() && ! $user->isConformite()) {
            abort(403, 'Seuls les administrateurs ou la conformité peuvent créer une fiche personne liée depuis le SI.');
        }

        $matricule = trim($validated['matricule']);
        $siData = $this->siLookup->lookup($matricule, $validated['type_client']);

        $request->session()->flash('sig_lookup_context', $validated['context']);

        if ($validated['context'] === 'membre_ca') {
            $request->session()->forget(['sig_lookup_si_data', 'sig_lookup_ca_not_found', 'sig_lookup_personnes_liees_si']);

            if (SigStaff::query()
                ->where(function ($q) use ($matricule, $siData) {
                    $cle = trim((string) ($siData['matricule'] ?? $matricule));
                    $q->where('reference', $cle)
                        ->orWhere('numero_client_si', $cle)
                        ->orWhere('reference', $matricule)
                        ->orWhere('numero_client_si', $matricule);
                })
                ->exists()) {
                return redirect()
                    ->route('suivi-signature.staff.manuel.create')
                    ->withErrors(['matricule' => 'Ce numéro client est déjà enregistré dans le suivi signature.']);
            }

            if ($siData === null) {
                $request->session()->put('sig_lookup_ca_not_found', $matricule);

                return redirect()->route('suivi-signature.staff.manuel.create');
            }

            $request->session()->put('sig_lookup_si_data', $siData);

            return redirect()->route('suivi-signature.staff.manuel.create');
        }

        $createRoute = $validated['context'] === 'staff'
            ? 'suivi-signature.staff.create'
            : 'suivi-signature.personnes-liees.create';

        if ($siData === null) {
            return redirect()
                ->route($createRoute)
                ->withErrors(['matricule' => 'Aucune donnée trouvée dans le SI pour ce numéro client.']);
        }

        if ($validated['context'] === 'staff') {
            if (SigStaff::query()->where('reference', $siData['matricule'])->exists()) {
                return redirect()
                    ->route($createRoute)
                    ->withErrors(['matricule' => 'Ce numéro est déjà enregistré comme fiche staff dans le suivi signature.']);
            }

            $personnesLieesSi = $this->siLookup->personnesLieesSiPourMatricule($matricule);

            $request->session()->put('sig_lookup_si_data', $siData);
            $request->session()->put('sig_lookup_personnes_liees_si', $personnesLieesSi);

            return redirect()->route($createRoute);
        }

        if (SigPersonneLiee::query()->where('numero_client', $siData['matricule'])->exists()) {
            return redirect()
                ->route($createRoute)
                ->withErrors(['matricule' => 'Cette fiche est déjà enregistrée dans le suivi signature.']);
        }

        $request->session()->put('sig_lookup_si_data', $siData);

        return redirect()->route($createRoute);
    }

    /**
     * Résolution SI puis alignement local : la fiche « personne liée » est créée automatiquement à partir du SI si besoin,
     * puis renvoyée pour attachement au staff (pas de création manuelle par le collaborateur).
     */
    public function resolvePersonneLieeParMatricule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'matricule' => 'required|string|max:100',
            'type_client' => 'required|in:personnel,entreprise',
        ]);

        $user = $request->user();
        if (! $user || ! $user->peutDeclarerPersonnesLieesSig()) {
            abort(403);
        }

        $staffFiche = $user->sigStaffFiche();
        if (
            $staffFiche
            && $staffFiche->doitSynchroniserClientSiAvantLiens()
            && ! $user->isAdmin()
            && ! $user->isConformite()
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Saisissez d’abord votre numéro client SI sur « Mes personnes liées » pour charger votre KYC et votre encours propre, puis vous pourrez lier des personnes.',
            ], 422);
        }

        $matricule = trim($validated['matricule']);
        $siData = $this->siLookup->lookup($matricule, $validated['type_client']);

        if ($siData === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Aucune donnée trouvée dans le SI pour ce numéro client.',
            ], 422);
        }

        try {
            $personne = SigPersonneLiee::ensureFromSiData($siData);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'siData' => $siData,
            'personneLiee' => $personne->only([
                'id', 'numero_client', 'prenom', 'nom', 'raison_sociale', 'est_personne_morale', 'encours_credit',
            ]),
        ]);
    }
}
