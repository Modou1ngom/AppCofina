<?php

namespace App\Http\Controllers;

use App\Models\Agence;
use App\Models\Filiale;
use App\Models\Profil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AgenceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = (int) $request->get('per_page', 5);

        $query = Agence::query();

        if ($user) {
            $user->applyFilialeScopeToQuery($query);
        } else {
            $query->whereRaw('0 = 1');
        }

        $agences = $query->orderBy('nom')->paginate($perPage);

        // Compter le nombre de profils par agence
        $agences->each(function ($agence) {
            $agence->profils_count = Profil::where('site', $agence->nom)->count();
        });

        return Inertia::render('agences/Index', [
            'agences' => $agences,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        $profilsQuery = Profil::query();
        $user?->applyProfilVisibilityScope($profilsQuery);
        $profils = $profilsQuery->orderBy('nom')->get(['id', 'nom', 'prenom', 'matricule']);
        $filiales = $user
            ? $user->visibleFilialesQuery()->get(['id', 'nom'])
            : collect();

        return Inertia::render('agences/Create', [
            'profils' => $profils,
            'filiales' => $filiales,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $request->merge($this->normalizedGpsInput($request));

        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:agences,nom',
            'code_agent' => 'required|string|max:50|unique:agences,code_agent',
            'description' => 'nullable|string',
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'actif' => 'required|in:actif,inactif',
            'chef_agence_id' => 'nullable|exists:profiles,id',
            'filiale_id' => 'nullable|exists:filiales,id',
        ]);

        if ($user && ! $user->isSuperAdmin()) {
            $filialeId = isset($validated['filiale_id']) ? (int) $validated['filiale_id'] : $user->primaryFilialeId();
            if (! $filialeId || ! $user->canAccessFiliale($filialeId)) {
                abort(403, 'Accès non autorisé à cette filiale.');
            }
            $validated['filiale_id'] = $filialeId;
        }

        Agence::create([
            'nom' => $validated['nom'],
            'code_agent' => $validated['code_agent'],
            'description' => $validated['description'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'actif' => $validated['actif'] === 'actif',
            'chef_agence_id' => $validated['chef_agence_id'] ?? null,
            'filiale_id' => $validated['filiale_id'] ?? null,
        ]);

        return redirect()->route('agences.index')
            ->with('success', 'Agence créée avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(Agence $agence)
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && ! $user->canAccessFiliale($agence->filiale_id ? (int) $agence->filiale_id : null)) {
            abort(403, 'Accès non autorisé à cette agence.');
        }

        $agence->load('chefAgence');
        $profilsQuery = Profil::where('site', $agence->nom);
        $user?->applyProfilVisibilityScope($profilsQuery);
        $profils = $profilsQuery->get();

        return Inertia::render('agences/Show', [
            'agence' => $agence,
            'profils' => $profils,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Agence $agence)
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && ! $user->canAccessFiliale($agence->filiale_id ? (int) $agence->filiale_id : null)) {
            abort(403, 'Accès non autorisé à cette agence.');
        }

        $profilsQuery = Profil::query();
        $user?->applyProfilVisibilityScope($profilsQuery);
        $profils = $profilsQuery->orderBy('nom')->get(['id', 'nom', 'prenom', 'matricule']);
        $filiales = $user
            ? $user->visibleFilialesQuery()->get(['id', 'nom'])
            : collect();

        return Inertia::render('agences/Edit', [
            'agence' => $agence,
            'profils' => $profils,
            'filiales' => $filiales,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Agence $agence)
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && ! $user->canAccessFiliale($agence->filiale_id ? (int) $agence->filiale_id : null)) {
            abort(403, 'Accès non autorisé à cette agence.');
        }

        $request->merge($this->normalizedGpsInput($request));

        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:agences,nom,'.$agence->id,
            'code_agent' => 'required|string|max:50|unique:agences,code_agent,'.$agence->id,
            'description' => 'nullable|string',
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'actif' => 'required|in:actif,inactif',
            'chef_agence_id' => 'nullable|exists:profiles,id',
            'filiale_id' => 'nullable|exists:filiales,id',
        ]);

        if ($user && ! $user->isSuperAdmin()) {
            $filialeId = isset($validated['filiale_id']) ? (int) $validated['filiale_id'] : $user->primaryFilialeId();
            if (! $filialeId || ! $user->canAccessFiliale($filialeId)) {
                abort(403, 'Accès non autorisé à cette filiale.');
            }
            $validated['filiale_id'] = $filialeId;
        }

        $agence->update([
            'nom' => $validated['nom'],
            'code_agent' => $validated['code_agent'],
            'description' => $validated['description'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'actif' => $validated['actif'] === 'actif',
            'chef_agence_id' => $validated['chef_agence_id'] ?? null,
            'filiale_id' => $validated['filiale_id'] ?? null,
        ]);

        return redirect()->route('agences.index')
            ->with('success', 'Agence mise à jour avec succès !');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Agence $agence)
    {
        $agence->delete();

        return redirect()->route('agences.index')
            ->with('success', 'Agence supprimée avec succès !');
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    private function normalizedGpsInput(Request $request): array
    {
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');
        $latNull = $lat === null || $lat === '';
        $lngNull = $lng === null || $lng === '';

        if ($latNull && $lngNull) {
            return ['latitude' => null, 'longitude' => null];
        }

        return [
            'latitude' => is_numeric($lat) ? (float) $lat : null,
            'longitude' => is_numeric($lng) ? (float) $lng : null,
        ];
    }
}
