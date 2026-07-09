<?php

namespace App\Http\Controllers;

use App\Models\Filiale;
use App\Models\Profil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FilialeController extends Controller
{
    private function ensureSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403, 'Seul le super administrateur peut gérer les filiales.');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->ensureSuperAdmin();

        $filiales = Filiale::orderBy('nom')->get();

        $filiales->each(function ($filiale) {
            $filiale->profils_count = Profil::where('site', $filiale->nom)->count();
        });

        return Inertia::render('filiales/Index', [
            'filiales' => $filiales,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->ensureSuperAdmin();

        return Inertia::render('filiales/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:filiales,nom',
            'description' => 'nullable|string',
            'actif' => 'required|in:actif,inactif',
        ]);

        Filiale::create([
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'actif' => $validated['actif'] === 'actif',
        ]);

        return redirect()->route('filiales.index')
            ->with('success', 'Filiale créée avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(Filiale $filiale)
    {
        $this->ensureSuperAdmin();

        $profils = Profil::where('site', $filiale->nom)->get();

        return Inertia::render('filiales/Show', [
            'filiale' => $filiale,
            'profils' => $profils,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Filiale $filiale)
    {
        $this->ensureSuperAdmin();

        return Inertia::render('filiales/Edit', [
            'filiale' => $filiale,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Filiale $filiale)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:filiales,nom,'.$filiale->id,
            'description' => 'nullable|string',
            'actif' => 'required|in:actif,inactif',
        ]);

        $filiale->update([
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'actif' => $validated['actif'] === 'actif',
        ]);

        return redirect()->route('filiales.index')
            ->with('success', 'Filiale mise à jour avec succès !');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Filiale $filiale)
    {
        $this->ensureSuperAdmin();

        $filiale->delete();

        return redirect()->route('filiales.index')
            ->with('success', 'Filiale supprimée avec succès !');
    }
}
