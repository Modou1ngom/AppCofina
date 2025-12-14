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
use Illuminate\Support\Facades\Auth;
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
        $user = Auth::user();
        
        $query = User::with(['profil', 'roles', 'filiales']);

        // Filtrage automatique selon le rôle de l'utilisateur connecté
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $isAdmin = $user && $user->isAdmin();
        
        // Super admin voit tous les utilisateurs
        if (!$isSuperAdmin) {
            // Admin normal voit uniquement les utilisateurs de ses filiales assignées
            if ($isAdmin && $user) {
                $userFilialesIds = $user->filiales()->get()->pluck('id')->toArray();
                if (!empty($userFilialesIds)) {
                    // Filtrer les utilisateurs qui ont au moins une filiale en commun avec l'admin
                    // Soit via la table pivot user_filiale, soit via leur profil (filiale_id)
                    $query->where(function($q) use ($userFilialesIds) {
                        $q->whereHas('filiales', function($subQ) use ($userFilialesIds) {
                            $subQ->whereIn('filiales.id', $userFilialesIds);
                        })->orWhereHas('profil', function($subQ) use ($userFilialesIds) {
                            $subQ->whereIn('profiles.filiale_id', $userFilialesIds);
                        });
                    });
                } else {
                    // Si l'admin n'a aucune filiale assignée, il ne voit rien
                    $query->where('id', 0);
                }
            }
            // Pour les autres utilisateurs, filtrer par leurs filiales assignées ou leur profil
            else {
                $userFilialesIds = [];
                
                // Récupérer les filiales assignées à l'utilisateur
                if ($user) {
                    $userFilialesIds = $user->filiales()->get()->pluck('id')->toArray();
                    
                    // Si l'utilisateur a un profil avec une filiale_id, l'ajouter aussi
                    $userProfil = $user->profil;
                    if ($userProfil && $userProfil->filiale_id) {
                        if (!in_array($userProfil->filiale_id, $userFilialesIds)) {
                            $userFilialesIds[] = $userProfil->filiale_id;
                        }
                    }
                }
                
                if (!empty($userFilialesIds)) {
                    // Filtrer les utilisateurs qui ont au moins une filiale en commun
                    $query->where(function($q) use ($userFilialesIds) {
                        $q->whereHas('filiales', function($subQ) use ($userFilialesIds) {
                            $subQ->whereIn('filiales.id', $userFilialesIds);
                        })->orWhereHas('profil', function($subQ) use ($userFilialesIds) {
                            $subQ->whereIn('profiles.filiale_id', $userFilialesIds);
                        });
                    });
                } else {
                    // Si l'utilisateur n'a aucune filiale assignée, il ne voit que lui-même
                    $query->where('id', $user ? $user->id : 0);
                }
            }
        }

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

        // Filtre par environnement (filiale) - seulement si l'utilisateur n'est pas super admin
        // Le super admin peut filtrer manuellement, mais les admins normaux sont déjà filtrés par leurs filiales
        if ($request->has('environnement') && $request->environnement) {
            $filiale = Filiale::find($request->environnement);
            if ($filiale) {
                // Pour le super admin, on peut filtrer par filiale via profil
                // Pour les admins normaux, on filtre parmi leurs filiales assignées
                if ($isSuperAdmin) {
                    $query->whereHas('profil', function($q) use ($filiale) {
                        $q->where('profiles.filiale_id', $filiale->id);
                    });
                } elseif ($isAdmin && $user) {
                    // Vérifier que la filiale demandée fait partie des filiales assignées à l'admin
                    $userFilialesIds = $user->filiales()->get()->pluck('id')->toArray();
                    if (in_array($filiale->id, $userFilialesIds)) {
                        $query->whereHas('filiales', function($q) use ($filiale) {
                            $q->where('filiales.id', $filiale->id);
                        });
                    } else {
                        // Si la filiale demandée n'est pas dans les filiales assignées, aucun résultat
                        $query->where('id', 0);
                    }
                }
            }
        }

        $users = $query->orderBy('name')->paginate($perPage);

        // Récupérer les données pour les filtres
        $roles = Role::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        $profils = Profil::orderBy('nom')->orderBy('prenom')->get(['id', 'nom', 'prenom', 'matricule']);
        // Filtrer les agences selon l'environnement de l'utilisateur
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $isAdmin = $user && $user->isAdmin();
        $isRh = $user && $user->isRh();
        
        $agencesQuery = Agence::where('actif', true);
        
        // Super admin voit toutes les agences
        if (!$isSuperAdmin) {
            // Admin normal et RH voient uniquement les agences de leurs filiales assignées
            if (($isAdmin || $isRh) && $user) {
                $userFilialesIds = $user->filiales()->get()->pluck('id')->toArray();
                if (!empty($userFilialesIds)) {
                    $agencesQuery->whereIn('filiale_id', $userFilialesIds);
                } else {
                    $agencesQuery->where('id', 0);
                }
            }
            // Les autres utilisateurs voient les agences de leurs filiales assignées ou de leur profil
            elseif ($user) {
                $userFilialesIds = $user->filiales()->get()->pluck('id')->toArray();
                $userProfil = $user->profil;
                
                if ($userProfil && $userProfil->filiale_id) {
                    if (!in_array($userProfil->filiale_id, $userFilialesIds)) {
                        $userFilialesIds[] = $userProfil->filiale_id;
                    }
                }
                
                if (!empty($userFilialesIds)) {
                    $agencesQuery->whereIn('filiale_id', $userFilialesIds);
                } else {
                    $agencesQuery->where('id', 0);
                }
            }
        }
        
        $agences = $agencesQuery->orderBy('nom')->get(['id', 'nom']);
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
        $filiales = Filiale::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        
        return Inertia::render('users/Create', [
            'roles' => $roles,
            'filiales' => $filiales,
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
            'filiales' => 'nullable|array',
            'filiales.*' => 'required|integer|exists:filiales,id',
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

        // Attacher les filiales/environnements si fournis
        if (!empty($validated['filiales']) && is_array($validated['filiales'])) {
            $user->filiales()->sync(array_map('intval', $validated['filiales']));
        }

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur créé avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $user->load(['profil', 'roles', 'filiales']);
        
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
        $user->load(['roles', 'profil', 'filiales']);
        
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
            'filiales' => 'nullable|array',
            'filiales.*' => 'required|integer|exists:filiales,id',
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

        // Synchroniser les filiales/environnements
        if (isset($validated['filiales']) && is_array($validated['filiales'])) {
            $user->filiales()->sync(array_map('intval', $validated['filiales']));
        } else {
            $user->filiales()->sync([]);
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

