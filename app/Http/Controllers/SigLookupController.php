<?php

namespace App\Http\Controllers;

use App\Models\SigPersonneLiee;
use App\Models\SigStaff;
use App\Services\SigSiLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SigLookupController extends Controller
{
    public function __construct(
        private readonly SigSiLookupService $siLookup
    ) {}

    public function lookup(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'context' => 'required|in:staff,personne_liee',
            'matricule' => 'required|string|max:100',
            'type_client' => 'required|in:personnel,entreprise',
        ]);

        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if ($validated['context'] === 'staff' && ! $user->isAdmin() && ! $user->isConformite()) {
            abort(403, 'Seuls les administrateurs ou la conformité peuvent créer une fiche staff depuis le SI.');
        }

        if ($validated['context'] === 'personne_liee' && ! $user->isAdmin() && ! $user->isConformite()) {
            abort(403, 'Seuls les administrateurs ou la conformité peuvent créer une fiche personne liée depuis le SI.');
        }

        $matricule = trim($validated['matricule']);
        $siData = $this->siLookup->lookup($matricule, $validated['type_client']);

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

            return Inertia::render('suivi-signature/staff/Create', [
                'siData' => $siData,
                'lookupDone' => true,
                'personnesLieesSi' => $personnesLieesSi,
            ]);
        }

        if (SigPersonneLiee::query()->where('numero_client', $siData['matricule'])->exists()) {
            return redirect()
                ->route($createRoute)
                ->withErrors(['matricule' => 'Cette fiche est déjà enregistrée dans le suivi signature.']);
        }

        return Inertia::render('suivi-signature/personnes-liees/Create', [
            'siData' => $siData,
            'lookupDone' => true,
        ]);
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
