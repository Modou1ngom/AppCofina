<?php

namespace App\Http\Controllers;

use App\Models\SigPersonneLiee;
use App\Models\SigStaff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SigPersonneLieeController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,conformite')->only([
            'index', 'create', 'store', 'edit', 'update', 'destroy',
        ]);
    }

    public function index(Request $request): Response
    {
        $perPage = (int) $request->get('per_page', 10);
        $query = SigPersonneLiee::query()->orderByDesc('updated_at');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('raison_sociale', 'like', "%{$search}%")
                    ->orWhere('numero_client', 'like', "%{$search}%");
            });
        }

        $personnesLiees = $query->paginate($perPage)->withQueryString();

        return Inertia::render('suivi-signature/personnes-liees/Index', [
            'personnesLiees' => $personnesLiees,
            'filters' => [
                'search' => $request->get('search', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('suivi-signature/personnes-liees/Create', [
            'siData' => null,
            'lookupDone' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPayloadForStore($request);
        $validated['encours_credit'] = $validated['encours_credit'] ?? 0;
        unset($validated['si_confirmed']);

        SigPersonneLiee::create($validated);

        return redirect()->route('suivi-signature.personnes-liees.index')
            ->with('success', 'Personne liée enregistrée.');
    }

    public function show(Request $request, SigPersonneLiee $personneLiee): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }
        if (! $user->isAdmin() && ! $user->isConformite()) {
            $staff = $user->sigStaffFiche();
            if (! $staff || ! $staff->personnesLiees()->whereKey($personneLiee->id)->exists()) {
                abort(403);
            }
        }

        $personneLiee->load('staffs');

        return Inertia::render('suivi-signature/personnes-liees/Show', [
            'personneLiee' => $personneLiee,
        ]);
    }

    public function edit(SigPersonneLiee $personneLiee): Response
    {
        return Inertia::render('suivi-signature/personnes-liees/Edit', [
            'personneLiee' => $personneLiee,
        ]);
    }

    public function update(Request $request, SigPersonneLiee $personneLiee): RedirectResponse
    {
        $validated = $this->validatedPayloadForUpdate($request);
        $validated['encours_credit'] = $validated['encours_credit'] ?? 0;

        $personneLiee->update($validated);

        return redirect()->route('suivi-signature.personnes-liees.show', $personneLiee)
            ->with('success', 'Personne liée mise à jour.');
    }

    public function destroy(SigPersonneLiee $personneLiee): RedirectResponse
    {
        $staffIds = $personneLiee->staffs->pluck('id');
        $personneLiee->delete();
        SigStaff::query()->whereIn('id', $staffIds)->get()->each->synchroniserEncoursTotaux();

        return redirect()->route('suivi-signature.personnes-liees.index')
            ->with('success', 'Personne liée supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayloadForStore(Request $request): array
    {
        $morale = $request->boolean('est_personne_morale');

        return $request->validate([
            'si_confirmed' => 'required|accepted',
            'numero_client' => 'required|string|max:100|unique:sig_personnes_liees,numero_client',
            'est_personne_morale' => 'required|boolean',
            'prenom' => Rule::when(! $morale, ['required', 'string', 'max:255'], ['nullable', 'string', 'max:255']),
            'nom' => Rule::when(! $morale, ['required', 'string', 'max:255'], ['nullable', 'string', 'max:255']),
            'raison_sociale' => Rule::when($morale, ['required', 'string', 'max:255'], ['nullable', 'string', 'max:255']),
            'kyc_piece_identite' => 'nullable|string|max:255',
            'kyc_adresse' => 'nullable|string',
            'kyc_telephone' => 'nullable|string|max:50',
            'encours_credit' => 'nullable|numeric|min:0',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayloadForUpdate(Request $request): array
    {
        $morale = $request->boolean('est_personne_morale');

        return $request->validate([
            'est_personne_morale' => 'required|boolean',
            'prenom' => Rule::when(! $morale, ['required', 'string', 'max:255'], ['nullable', 'string', 'max:255']),
            'nom' => Rule::when(! $morale, ['required', 'string', 'max:255'], ['nullable', 'string', 'max:255']),
            'raison_sociale' => Rule::when($morale, ['required', 'string', 'max:255'], ['nullable', 'string', 'max:255']),
            'kyc_piece_identite' => 'nullable|string|max:255',
            'kyc_adresse' => 'nullable|string',
            'kyc_telephone' => 'nullable|string|max:50',
            'encours_credit' => 'nullable|numeric|min:0',
        ]);
    }
}
