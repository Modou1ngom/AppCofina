<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Profil;
use App\Models\Filiale;
use App\Models\Departement;
use App\Models\Agence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 5);
        
        $query = User::with(['profil', 'roles']);

        // Filtre par recherche (nom, email)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtre par activation
        if ($request->has('activation') && $request->activation !== '') {
            $query->where('is_active', (bool) $request->activation);
        }

        // Filtre par rôle
        if ($request->has('role') && $request->role) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('roles.id', $request->role);
            });
        }

        // Filtre par profil
        if ($request->has('profil') && $request->profil) {
            $query->whereHas('profil', function($q) use ($request) {
                $q->where('profiles.id', $request->profil);
            });
        }

        // Filtre par agence (via profil)
        if ($request->has('agence') && $request->agence) {
            $agence = Agence::find($request->agence);
            if ($agence) {
                $query->whereHas('profil', function($q) use ($agence) {
                    $q->where('profiles.site', $agence->nom);
                });
            }
        }

        // Filtre par département (via profil)
        if ($request->has('departement') && $request->departement) {
            $departement = Departement::find($request->departement);
            if ($departement) {
                $query->whereHas('profil', function($q) use ($departement) {
                    $q->where('profiles.departement', $departement->nom);
                });
            }
        }

        // Filtre par environnement (filiale via profil)
        if ($request->has('environnement') && $request->environnement) {
            $filiale = Filiale::find($request->environnement);
            if ($filiale) {
                $query->whereHas('profil', function($q) use ($filiale) {
                    $q->where('profiles.site', $filiale->nom);
                });
            }
        }

        $users = $query->orderBy('name')->paginate($perPage);

        // Récupérer les données pour les filtres
        $roles = Role::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        $profils = Profil::orderBy('nom')->orderBy('prenom')->get(['id', 'nom', 'prenom', 'matricule']);
        $agences = Agence::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        $departements = Departement::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        $filiales = Filiale::where('actif', true)->orderBy('nom')->get(['id', 'nom']);

        return Inertia::render('users/Index', [
            'users' => $users,
            'roles' => $roles,
            'profils' => $profils,
            'agences' => $agences,
            'departements' => $departements,
            'environnements' => $filiales, // Utiliser filiales comme environnements
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = Role::where('actif', true)->orderBy('nom')->get();
        
        return Inertia::render('users/Create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'nullable|array',
            'roles.*' => 'required|integer|exists:roles,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Attacher les rôles si fournis
        if (!empty($validated['roles']) && is_array($validated['roles'])) {
            $user->roles()->sync(array_map('intval', $validated['roles']));
        }

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur créé avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $user->load(['profil', 'roles']);
        
        return Inertia::render('users/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $roles = Role::where('actif', true)->orderBy('nom')->get();
        $profils = Profil::orderBy('nom')->orderBy('prenom')->get(['id', 'nom', 'prenom', 'matricule', 'email', 'site']);
        $filiales = Filiale::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        $user->load(['roles', 'profil']);
        
        return Inertia::render('users/Edit', [
            'user' => $user,
            'roles' => $roles,
            'profils' => $profils,
            'filiales' => $filiales,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'nullable|array',
            'roles.*' => 'required|integer|exists:roles,id',
            'profil_id' => 'nullable|integer|exists:profiles,id',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        // Mettre à jour le mot de passe seulement s'il est fourni
        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        // Synchroniser les rôles
        if (isset($validated['roles']) && is_array($validated['roles'])) {
            $user->roles()->sync(array_map('intval', $validated['roles']));
        } else {
            $user->roles()->sync([]);
        }

        // Associer ou dissocier le profil
        // D'abord, dissocier le profil actuel si l'email correspond
        $currentProfil = Profil::where('email', $user->email)->first();
        if ($currentProfil && (!isset($validated['profil_id']) || $validated['profil_id'] != $currentProfil->id)) {
            // Dissocier en mettant l'email du profil actuel à null
            $currentProfil->update(['email' => null]);
        }

        // Associer le nouveau profil si sélectionné
        if (isset($validated['profil_id']) && !empty($validated['profil_id'])) {
            $profil = Profil::find($validated['profil_id']);
            if ($profil) {
                // Mettre à jour l'email du profil pour qu'il corresponde à l'email de l'utilisateur
                // Vérifier que l'email n'est pas déjà utilisé par un autre profil
                $existingProfil = Profil::where('email', $validated['email'])
                    ->where('id', '!=', $profil->id)
                    ->first();
                
                if (!$existingProfil) {
                    $profil->update(['email' => $validated['email']]);
                }
            }
        }

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur mis à jour avec succès !');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur supprimé avec succès !');
    }

    /**
     * Toggle the active status of a user.
     */
    public function toggle(User $user)
    {
        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'activé' : 'désactivé';
        
        return redirect()->route('users.index')
            ->with('success', "Utilisateur {$status} avec succès !");
    }
}

