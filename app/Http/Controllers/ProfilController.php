<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Profil;

class ProfilController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return redirect()->route('profils.create');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('profils.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
{
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'matricule' => 'required|string|max:50|unique:profiles,matricule',
            'fonction' => 'nullable|string',
            'departement' => 'nullable|string',
            'type_contrat' => 'nullable|in:CDI,CDD,Stagiaire,Autre',
        ]);

        $data = [
            'nom' => $validated['nom'],
            'prenom' => $validated['prenom'],
            'matricule' => $validated['matricule'],
            'fonction' => $validated['fonction'] ?? null,
            'departement' => $validated['departement'] ?? null,
        ];

        // Si type_contrat est vide ou invalide, on ne l'envoie pas pour laisser le défaut DB ('CDI')
        if (!empty($validated['type_contrat']) && in_array($validated['type_contrat'], ['CDI', 'CDD', 'Stagiaire', 'Autre'])) {
            $data['type_contrat'] = $validated['type_contrat'];
        }

        $profil = Profil::create($data);

    return redirect()->route('profils.index')->with('success', 'Profil créé avec succès !');
}


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
