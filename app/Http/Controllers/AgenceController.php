<?php

namespace App\Http\Controllers;

use App\Models\Agence;
use App\Models\Profil;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgenceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $agences = Agence::orderBy('nom')->get();
        
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
        $profils = Profil::orderBy('nom')->get(['id', 'nom', 'prenom', 'matricule']);
        
        return Inertia::render('agences/Create', [
            'profils' => $profils,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:agences,nom',
            'code_agent' => 'required|string|max:50|unique:agences,code_agent',
            'description' => 'nullable|string',
            'actif' => 'required|in:actif,inactif',
            'chef_agence_id' => 'nullable|exists:profiles,id',
        ]);

        Agence::create([
            'nom' => $validated['nom'],
            'code_agent' => $validated['code_agent'],
            'description' => $validated['description'] ?? null,
            'actif' => $validated['actif'] === 'actif',
            'chef_agence_id' => $validated['chef_agence_id'] ?? null,
        ]);

        return redirect()->route('agences.index')
            ->with('success', 'Agence créée avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(Agence $agence)
    {
        $agence->load('chefAgence');
        $profils = Profil::where('site', $agence->nom)->get();
        
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
        $profils = Profil::orderBy('nom')->get(['id', 'nom', 'prenom', 'matricule']);
        
        return Inertia::render('agences/Edit', [
            'agence' => $agence,
            'profils' => $profils,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Agence $agence)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:agences,nom,' . $agence->id,
            'code_agent' => 'required|string|max:50|unique:agences,code_agent,' . $agence->id,
            'description' => 'nullable|string',
            'actif' => 'required|in:actif,inactif',
            'chef_agence_id' => 'nullable|exists:profiles,id',
        ]);

        $agence->update([
            'nom' => $validated['nom'],
            'code_agent' => $validated['code_agent'],
            'description' => $validated['description'] ?? null,
            'actif' => $validated['actif'] === 'actif',
            'chef_agence_id' => $validated['chef_agence_id'] ?? null,
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
}
