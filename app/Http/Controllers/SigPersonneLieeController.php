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

    public function create(Request $request): Response
    {
        if ($request->boolean('reset')) {
            $request->session()->forget(['sig_lookup_si_data', 'sig_lookup_personnes_liees_si', 'sig_lookup_context']);
        }

        $siData = $request->session()->get('sig_lookup_si_data');

        $attachStaffId = $request->integer('staff_id') ?: $request->session()->get('sig_attach_staff_id');
        $attachStaff = null;
        if ($attachStaffId) {
            $attachStaff = SigStaff::query()->find($attachStaffId);
            if ($attachStaff) {
                $request->session()->put('sig_attach_staff_id', $attachStaff->id);
            } else {
                $request->session()->forget('sig_attach_staff_id');
                $attachStaffId = null;
            }
        }

        return Inertia::render('suivi-signature/personnes-liees/Create', [
            'siData' => $siData,
            'lookupDone' => $siData !== null,
            'attachStaff' => $attachStaff
                ? [
                    'id' => $attachStaff->id,
                    'reference' => $attachStaff->reference,
                    'prenom' => $attachStaff->prenom,
                    'nom' => $attachStaff->nom,
                ]
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPayloadForStore($request);
        $validated['encours_credit'] = $validated['encours_credit'] ?? 0;
        unset($validated['si_confirmed']);

        $personne = SigPersonneLiee::create($validated);

        $attachStaffId = $request->integer('attach_staff_id')
            ?: (int) $request->session()->get('sig_attach_staff_id');

        $request->session()->forget([
            'sig_lookup_si_data',
            'sig_lookup_personnes_liees_si',
            'sig_lookup_context',
            'sig_attach_staff_id',
        ]);

        if ($attachStaffId > 0) {
            $staff = SigStaff::query()->find($attachStaffId);
            if ($staff && ! $staff->personnesLiees()->whereKey($personne->id)->exists()) {
                $request->validate([
                    'type_relation' => ['required', 'string', 'max:255', \App\Support\SigTypeRelation::rule()],
                    'classe' => 'nullable|integer|min:1|max:4',
                ]);
                $typeRelation = trim((string) $request->input('type_relation'));
                $classe = max(1, min(4, (int) $request->input('classe', 1)));
                $staff->personnesLiees()->attach($personne->id, [
                    'type_relation' => $typeRelation,
                    'classe' => $classe,
                ]);
                $staff->synchroniserEncoursTotaux();

                return redirect()
                    ->route('suivi-signature.staff.lier-personnes', $staff)
                    ->with('success', 'Personne enregistrée et liée à ce signataire.');
            }
        }

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
