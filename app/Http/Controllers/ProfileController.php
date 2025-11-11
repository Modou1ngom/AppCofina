<?php

namespace App\Http\Controllers;

use App\Models\Profil as Profile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index()
    {
        return response()->json(Profile::orderBy('nom')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'matricule' => 'required|string|unique:profiles,matricule',
            'prenom' => 'required|string|max:100',
            'nom' => 'required|string|max:100',
            'fonction' => 'nullable|string|max:150',
            'departement' => 'nullable|string|max:150',
            'email' => 'nullable|email|unique:profiles,email',
            'telephone' => 'nullable|string|max:20',
            'site' => 'nullable|string|max:100',
            'type_contrat' => 'in:CDI,CDD,Stagiaire,Autre',
            'statut' => 'in:actif,inactif',
        ]);

        $profile = Profile::create($data);
        return response()->json($profile, 201);
    }

    public function show(Profile $profile)
    {
        return response()->json($profile);
    }

    public function update(Request $request, Profile $profile)
    {
        $data = $request->validate([
            'prenom' => 'sometimes|string|max:100',
            'nom' => 'sometimes|string|max:100',
            'fonction' => 'nullable|string|max:150',
            'departement' => 'nullable|string|max:150',
            'email' => 'nullable|email|unique:profiles,email,' . $profile->id,
            'telephone' => 'nullable|string|max:20',
            'site' => 'nullable|string|max:100',
            'type_contrat' => 'in:CDI,CDD,Stagiaire,Autre',
            'statut' => 'in:actif,inactif',
        ]);

        $profile->update($data);
        return response()->json($profile);
    }

    public function destroy(Profile $profile)
    {
        $profile->delete();
        return response()->json(['message' => 'Profil supprimé avec succès']);
    }
}


