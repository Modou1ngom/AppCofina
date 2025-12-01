<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Profil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with(['profil', 'roles'])
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('users/Index', [
            'users' => $users,
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
        $profils = Profil::orderBy('nom')->orderBy('prenom')->get(['id', 'nom', 'prenom', 'matricule', 'email']);
        $user->load(['roles', 'profil']);
        
        return Inertia::render('users/Edit', [
            'user' => $user,
            'roles' => $roles,
            'profils' => $profils,
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

