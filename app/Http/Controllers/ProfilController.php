<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Profil;
use App\Models\Role;
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
        
        // Admin voit tous les profils
        if ($user && $user->isAdmin()) {
            $profils = Profil::with('roles')
                ->orderBy('nom')
                ->orderBy('prenom')
                ->get();
        } else {
            // Les autres voient uniquement leur propre profil et leurs subordonnés
            $profil = $user?->profil;
            if ($profil) {
                $profils = Profil::with('roles')
                    ->where(function($query) use ($profil) {
                        $query->where('id', $profil->id)
                              ->orWhere('superieur_hierarchique_id', $profil->id);
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
        $roles = Role::where('actif', true)->orderBy('nom')->get();
        $profils = Profil::orderBy('nom')->get(['id', 'nom', 'prenom', 'matricule']);
        
        return Inertia::render('profils/Create', [
            'roles' => $roles,
            'profils' => $profils,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Debug: logger les données reçues
        \Log::info('Données reçues lors de la création de profil:', [
            'all' => $request->all(),
            'roles' => $request->input('roles'),
            'roles_type' => gettype($request->input('roles')),
        ]);
        
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'matricule' => 'required|string|max:50|unique:profiles,matricule',
            'fonction' => 'nullable|string',
            'departement' => 'nullable|string',
            'email' => 'nullable|email|unique:profiles,email',
            'telephone' => 'nullable|string|max:20',
            'site' => 'nullable|string|max:100',
            'type_contrat' => 'nullable|in:CDI,CDD,Stagiaire,Autre',
            'statut' => 'nullable|in:actif,inactif',
            'superieur_hierarchique_id' => 'nullable|exists:profiles,id',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);
        
        // Debug: logger les données validées
        \Log::info('Données validées:', [
            'roles' => $validated['roles'] ?? 'non défini',
            'roles_type' => isset($validated['roles']) ? gettype($validated['roles']) : 'non défini',
            'roles_count' => isset($validated['roles']) ? count($validated['roles']) : 0,
        ]);

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
            'superieur_hierarchique_id' => $validated['superieur_hierarchique_id'] ?? null,
        ];

        $profil = Profil::create($data);

        // Debug: Log les rôles validés
        \Log::info('Création profil - Rôles validés:', [
            'roles' => $validated['roles'] ?? null,
            'roles_empty' => empty($validated['roles'] ?? null),
            'roles_count' => count($validated['roles'] ?? []),
        ]);

        // Attacher les rôles si fournis
        // Vérifier que les rôles existent et ne sont pas vides
        if (!empty($validated['roles']) && is_array($validated['roles']) && count($validated['roles']) > 0) {
            \Log::info('Synchronisation des rôles:', ['roles' => $validated['roles']]);
            $profil->roles()->sync($validated['roles']);
        } else {
            \Log::warning('Aucun rôle à synchroniser', [
                'roles_received' => $validated['roles'] ?? null,
            ]);
        }

        return redirect()->route('profils.index')
            ->with('success', 'Profil créé avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(Profil $profil)
    {
        $profil->load(['roles', 'superieurHierarchique', 'subordonnes']);
        
        return Inertia::render('profils/Show', [
            'profil' => $profil,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Profil $profil)
    {
        $profil->load('roles');
        $roles = Role::where('actif', true)->orderBy('nom')->get();
        $profils = Profil::where('id', '!=', $profil->id)
            ->orderBy('nom')
            ->get(['id', 'nom', 'prenom', 'matricule']);
        
        return Inertia::render('profils/Edit', [
            'profil' => $profil,
            'roles' => $roles,
            'profils' => $profils,
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
            'telephone' => 'nullable|string|max:20',
            'site' => 'nullable|string|max:100',
            'type_contrat' => 'nullable|in:CDI,CDD,Stagiaire,Autre',
            'statut' => 'nullable|in:actif,inactif',
            'superieur_hierarchique_id' => 'nullable|exists:profiles,id',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $profil->update($validated);

        // Synchroniser les rôles si fournis
        if (isset($validated['roles'])) {
            $profil->roles()->sync($validated['roles']);
        }

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
