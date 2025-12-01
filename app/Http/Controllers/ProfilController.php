<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Profil;
use App\Models\Departement;
use App\Models\Agence;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class ProfilController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        
        // Admin et RH voient tous les profils
        if ($user && ($user->isAdmin() || $user->isRh())) {
            $profils = Profil::orderBy('nom')
                ->orderBy('prenom')
                ->get();
        } else {
            // Les autres voient uniquement leur propre profil et leurs subordonnés
            $profil = $user?->profil;
            if ($profil) {
                $profils = Profil::where(function($query) use ($profil) {
                        $query->where('id', $profil->id)
                              ->orWhere('n_plus_1_id', $profil->id);
                    })
                    ->orderBy('nom')
                    ->orderBy('prenom')
                    ->get();
            } else {
                $profils = collect([]);
            }
        }
        
        return Inertia::render('profils/Index', [
            'profils' => $profils,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $profils = Profil::orderBy('nom')->get(['id', 'nom', 'prenom', 'matricule']);
        $departements = Departement::where('actif', true)
            ->with('responsable:id,nom,prenom,matricule')
            ->orderBy('nom')
            ->get(['id', 'nom', 'responsable_departement_id']);
        $agences = Agence::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        
        return Inertia::render('profils/Create', [
            'profils' => $profils,
            'departements' => $departements,
            'agences' => $agences,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'fonction' => 'nullable|string',
                'departement' => 'nullable|string',
                'email' => 'nullable|email|unique:profiles,email',
                'telephone' => ['nullable', 'string', 'max:20', 'regex:/^(\\+221|00221|221)?[0-9]{9}$/'],
                'site' => 'nullable|string|max:100',
                'type_contrat' => 'nullable|in:CDI,CDD,Stagiaire,Autre',
                'statut' => 'nullable|in:actif,inactif',
                'n_plus_1_id' => 'nullable|exists:profiles,id',
                'n_plus_2_id' => 'nullable|exists:profiles,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        }
        
        // Vérifier que N+1 et N+2 ne sont pas la même personne
        if (!empty($validated['n_plus_1_id']) && !empty($validated['n_plus_2_id']) && $validated['n_plus_1_id'] == $validated['n_plus_2_id']) {
            return redirect()->back()
                ->withErrors(['n_plus_2_id' => 'Une personne ne peut pas être à la fois N+1 et N+2.'])
                ->withInput();
        }
        
        // Générer automatiquement le matricule
        $validated['matricule'] = Profil::generateMatricule();

        $data = [
            'nom' => $validated['nom'],
            'prenom' => $validated['prenom'],
            'matricule' => $validated['matricule'],
            'fonction' => $validated['fonction'] ?? null,
            'departement' => $validated['departement'] ?? null,
            'email' => $validated['email'] ?? null,
            'telephone' => $validated['telephone'] ?? null,
            'site' => $validated['site'] ?? null,
            'type_contrat' => $validated['type_contrat'] ?? 'CDI',
            'statut' => $validated['statut'] ?? 'actif',
            'n_plus_1_id' => $validated['n_plus_1_id'] ?? null,
            'n_plus_2_id' => $validated['n_plus_2_id'] ?? null,
        ];

        Profil::create($data);

        return redirect()->route('profils.index')
            ->with('success', 'Profil créé avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(Profil $profil)
    {
        $profil->load(['nPlus1', 'nPlus2']);
        
        return Inertia::render('profils/Show', [
            'profil' => $profil,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Profil $profil)
    {
        $profils = Profil::where('id', '!=', $profil->id)
            ->orderBy('nom')
            ->get(['id', 'nom', 'prenom', 'matricule']);
        $departements = Departement::where('actif', true)
            ->with('responsable:id,nom,prenom,matricule')
            ->orderBy('nom')
            ->get(['id', 'nom', 'responsable_departement_id']);
        $agences = Agence::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        
        return Inertia::render('profils/Edit', [
            'profil' => $profil,
            'profils' => $profils,
            'departements' => $departements,
            'agences' => $agences,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Profil $profil)
    {
        $validated = $request->validate([
            'nom' => 'sometimes|required|string|max:255',
            'prenom' => 'sometimes|required|string|max:255',
            'matricule' => 'sometimes|required|string|max:50|unique:profiles,matricule,' . $profil->id,
            'fonction' => 'nullable|string',
            'departement' => 'nullable|string',
            'email' => 'nullable|email|unique:profiles,email,' . $profil->id,
            'telephone' => ['nullable', 'string', 'max:20', 'regex:/^(\\+221|00221|221)?[0-9]{9}$/'],
            'site' => 'nullable|string|max:100',
            'type_contrat' => 'nullable|in:CDI,CDD,Stagiaire,Autre',
            'statut' => 'nullable|in:actif,inactif',
            'n_plus_1_id' => 'nullable|exists:profiles,id',
            'n_plus_2_id' => 'nullable|exists:profiles,id',
        ]);

        // Vérifier que N+1 et N+2 ne sont pas la même personne
        if (!empty($validated['n_plus_1_id']) && !empty($validated['n_plus_2_id']) && $validated['n_plus_1_id'] == $validated['n_plus_2_id']) {
            return redirect()->back()
                ->withErrors(['n_plus_2_id' => 'Une personne ne peut pas être à la fois N+1 et N+2.'])
                ->withInput();
        }

        $profil->update($validated);

        return redirect()->route('profils.index')
            ->with('success', 'Profil mis à jour avec succès !');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Profil $profil)
    {
        $profil->delete();
        
        return redirect()->route('profils.index')
            ->with('success', 'Profil supprimé avec succès !');
    }
}
