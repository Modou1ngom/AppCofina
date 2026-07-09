<?php

namespace App\Http\Controllers;

use App\Models\Agence;
use App\Models\Departement;
use App\Models\Filiale;
use App\Models\Profil;
use App\Models\Role;
use App\Models\User;
use App\Support\ProfilDepartementRoleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $user = Auth::user();

        $query = User::with(['profil', 'roles', 'filiales', 'agences']);

        if ($user) {
            $user->applyUserVisibilityScope($query);
        }

        $isSuperAdmin = $user && $user->isSuperAdmin();

        // Filtre par recherche (nom, email)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
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
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('roles.id', $request->role);
            });
        }

        // Filtre par profil
        if ($request->has('profil') && $request->profil) {
            $query->whereHas('profil', function ($q) use ($request) {
                $q->where('profiles.id', $request->profil);
            });
        }

        // Filtre par agence (via profil)
        if ($request->has('agence') && $request->agence) {
            $agence = Agence::find($request->agence);
            if ($agence) {
                $query->where(function ($q) use ($agence) {
                    $q->whereHas('agences', function ($subQ) use ($agence) {
                        $subQ->where('agences.id', $agence->id);
                    })->orWhereHas('profil', function ($subQ) use ($agence) {
                        // Compatibilite ascendante avec l'agence stockee sur le profil.
                        $subQ->where('profiles.site', $agence->nom);
                    });
                });
            }
        }

        // Filtre par département (via profil)
        if ($request->has('departement') && $request->departement) {
            $departement = Departement::find($request->departement);
            if ($departement) {
                $query->whereHas('profil', function ($q) use ($departement) {
                    $q->where('profiles.departement', $departement->nom);
                });
            }
        }

        // Filtre par environnement (filiale) - seulement si l'utilisateur n'est pas super admin
        // Le super admin peut filtrer manuellement, mais les admins normaux sont déjà filtrés par leurs filiales
        if ($request->has('environnement') && $request->environnement) {
            $filiale = Filiale::find($request->environnement);
            if ($filiale && ($isSuperAdmin || $user?->canAccessFiliale((int) $filiale->id))) {
                $query->whereHas('profil', function ($q) use ($filiale) {
                    $q->where('profiles.filiale_id', $filiale->id);
                });
            } elseif ($filiale) {
                $query->where('id', 0);
            }
        }

        $users = $query->orderBy('name')->paginate($perPage);

        // Récupérer les données pour les filtres
        $roles = Role::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        $profilsQuery = Profil::query();
        if ($user) {
            $user->applyProfilVisibilityScope($profilsQuery);
        }
        $profils = $profilsQuery->orderBy('nom')->orderBy('prenom')->get(['id', 'nom', 'prenom', 'matricule']);

        $agencesQuery = Agence::where('actif', true);
        if ($user) {
            $user->applyFilialeScopeToQuery($agencesQuery);
        }
        $agences = $agencesQuery->orderBy('nom')->get(['id', 'nom']);
        $departements = Departement::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        $filiales = $user
            ? $user->visibleFilialesQuery()->get(['id', 'nom'])
            : Filiale::where('actif', true)->orderBy('nom')->get(['id', 'nom']);

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
        $user = Auth::user();
        $filiales = $user
            ? $user->visibleFilialesQuery()->get(['id', 'nom'])
            : collect();
        $profilsQuery = Profil::query();
        $user?->applyProfilVisibilityScope($profilsQuery);
        $profils = $profilsQuery->orderBy('nom')->orderBy('prenom')->get([
            'id', 'nom', 'prenom', 'matricule', 'email', 'site', 'filiale_id', 'departement',
        ]);
        $roles = $this->assignableRoles();

        return Inertia::render('users/Create', [
            'filiales' => $filiales,
            'profils' => $profils,
            'roles' => $roles,
            'departementRoleMap' => config('cofina.departement_role_map', []),
            'defaultDepartementRole' => config('cofina.default_departement_role', 'metier'),
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
            'must_change_password' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'required|integer|exists:roles,id',
            'profil_id' => 'required|integer|exists:profiles,id',
            'filiales' => 'nullable|array',
            'filiales.*' => 'required|integer|exists:filiales,id',
            'agences' => 'nullable|array',
            'agences.*' => 'required|integer|exists:agences,id',
            'default_agence_id' => 'nullable|integer|exists:agences,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'must_change_password' => $validated['must_change_password'] ?? true,
        ]);

        // Associer le profil si sélectionné (avant l'attachement des filiales)
        $profilFilialeId = null;
        $profil = null;
        if (isset($validated['profil_id']) && ! empty($validated['profil_id'])) {
            $profil = Profil::find($validated['profil_id']);
            if (! $profil || ! Auth::user()?->canAccessProfil($profil)) {
                abort(403, 'Accès non autorisé à ce profil collaborateur.');
            }
            if ($profil) {
                // Récupérer la filiale du profil pour l'ajouter aux environnements
                if ($profil->filiale_id) {
                    $profilFilialeId = $profil->filiale_id;
                }

                // Vérifier que l'email n'est pas déjà utilisé par un autre profil
                $existingProfil = Profil::where('email', $validated['email'])
                    ->where('id', '!=', $profil->id)
                    ->first();

                if (! $existingProfil) {
                    // Mettre à jour l'email du profil pour qu'il corresponde à l'email de l'utilisateur
                    $profil->update(['email' => $validated['email']]);
                }
            }
        }

        $roleIds = [];
        if (! empty($validated['roles']) && is_array($validated['roles'])) {
            $roleIds = array_map('intval', $validated['roles']);
        } elseif ($profil !== null) {
            $roleIds = $this->roleIdsFromProfilDepartement($profil);
        }

        if ($roleIds !== []) {
            $user->roles()->sync($roleIds);
        }

        // Attacher les filiales/environnements
        $filialesToAttach = $this->resolveFilialesForUser($validated['filiales'] ?? [], $profilFilialeId);

        if (! empty($filialesToAttach)) {
            $user->filiales()->sync($filialesToAttach);
        }

        // Attacher les agences si fournies.
        if (! empty($validated['agences']) && is_array($validated['agences'])) {
            $agenceIds = array_map('intval', $validated['agences']);
            $defaultAgenceId = isset($validated['default_agence_id']) ? (int) $validated['default_agence_id'] : null;

            if ($defaultAgenceId !== null && ! in_array($defaultAgenceId, $agenceIds, true)) {
                return back()->withErrors([
                    'default_agence_id' => 'L\'agence domiciliaire doit appartenir aux agences sélectionnées.',
                ])->withInput();
            }

            if ($defaultAgenceId === null) {
                $defaultAgenceId = $agenceIds[0] ?? null;
            }

            $syncData = [];
            foreach ($agenceIds as $agenceId) {
                $syncData[$agenceId] = [
                    'is_default' => $defaultAgenceId !== null && $agenceId === $defaultAgenceId,
                ];
            }

            $user->agences()->sync($syncData);
        }

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur créé avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $this->authorizeUserAccess($user);
        $user->load(['profil', 'roles', 'filiales', 'agences']);

        return Inertia::render('users/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $this->authorizeUserAccess($user);

        $authUser = Auth::user();
        $profilsQuery = Profil::query();
        $authUser?->applyProfilVisibilityScope($profilsQuery);
        $profils = $profilsQuery->orderBy('nom')->orderBy('prenom')->get([
            'id', 'nom', 'prenom', 'matricule', 'email', 'site', 'filiale_id', 'departement',
        ]);
        $filiales = $authUser
            ? $authUser->visibleFilialesQuery()->get(['id', 'nom'])
            : collect();
        $user->load(['roles', 'profil', 'filiales', 'agences']);
        $roles = $this->assignableRoles();
        $missingRoleIds = $user->roles->pluck('id')->diff($roles->pluck('id'));
        if ($missingRoleIds->isNotEmpty()) {
            $roles = $roles
                ->merge(Role::query()->whereIn('id', $missingRoleIds)->get(['id', 'nom', 'slug']))
                ->sortBy('nom')
                ->values();
        }

        return Inertia::render('users/Edit', [
            'user' => $user,
            'profils' => $profils,
            'filiales' => $filiales,
            'roles' => $roles,
            'departementRoleMap' => config('cofina.departement_role_map', []),
            'defaultDepartementRole' => config('cofina.default_departement_role', 'metier'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorizeUserAccess($user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'must_change_password' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'required|integer|exists:roles,id',
            'profil_id' => 'nullable|integer|exists:profiles,id',
            'filiales' => 'nullable|array',
            'filiales.*' => 'required|integer|exists:filiales,id',
            'agences' => 'nullable|array',
            'agences.*' => 'required|integer|exists:agences,id',
            'default_agence_id' => 'nullable|integer|exists:agences,id',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'must_change_password' => $validated['must_change_password'] ?? false,
        ];

        // Mettre à jour le mot de passe seulement s'il est fourni
        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        // Associer ou dissocier le profil (avant la synchronisation des filiales)
        $profilFilialeId = null;
        $profil = null;

        // D'abord, dissocier le profil actuel si l'email correspond
        $currentProfil = Profil::where('email', $user->email)->first();
        if ($currentProfil && (! isset($validated['profil_id']) || $validated['profil_id'] != $currentProfil->id)) {
            // Dissocier en mettant l'email du profil actuel à null
            $currentProfil->update(['email' => null]);
        }

        // Associer le nouveau profil si sélectionné
        if (isset($validated['profil_id']) && ! empty($validated['profil_id'])) {
            $profil = Profil::find($validated['profil_id']);
            if (! $profil || ! Auth::user()?->canAccessProfil($profil)) {
                abort(403, 'Accès non autorisé à ce profil collaborateur.');
            }
            if ($profil) {
                // Récupérer la filiale du profil pour l'ajouter aux environnements
                if ($profil->filiale_id) {
                    $profilFilialeId = $profil->filiale_id;
                }

                // Mettre à jour l'email du profil pour qu'il corresponde à l'email de l'utilisateur
                // Vérifier que l'email n'est pas déjà utilisé par un autre profil
                $existingProfil = Profil::where('email', $validated['email'])
                    ->where('id', '!=', $profil->id)
                    ->first();

                if (! $existingProfil) {
                    $profil->update(['email' => $validated['email']]);
                }
            }
        }

        $roleIds = [];
        if (! empty($validated['roles']) && is_array($validated['roles'])) {
            $roleIds = array_map('intval', $validated['roles']);
        } elseif ($profil !== null) {
            $roleIds = $this->roleIdsFromProfilDepartement($profil);
        }

        $user->roles()->sync($roleIds);

        // Synchroniser les filiales/environnements
        $filialesToAttach = $this->resolveFilialesForUser(
            isset($validated['filiales']) && is_array($validated['filiales'])
                ? $validated['filiales']
                : $user->filiales()->pluck('filiales.id')->map(fn ($id) => (int) $id)->all(),
            $profilFilialeId,
        );

        $user->filiales()->sync($filialesToAttach);

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur mis à jour avec succès !');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorizeUserAccess($user);
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur supprimé avec succès !');
    }

    /**
     * Toggle the active status of a user.
     */
    public function toggle(User $user)
    {
        $this->authorizeUserAccess($user);
        $user->is_active = ! $user->is_active;
        $user->save();

        $status = $user->is_active ? 'activé' : 'désactivé';

        return redirect()->route('users.index')
            ->with('success', "Utilisateur {$status} avec succès !");
    }

    private function authorizeUserAccess(User $target): void
    {
        $user = Auth::user();
        if (! $user || ! $user->canAccessUser($target)) {
            abort(403, 'Accès non autorisé à cet utilisateur.');
        }
    }

    /**
     * @param  list<int|string>  $requestedFiliales
     * @return list<int>
     */
    private function resolveFilialesForUser(array $requestedFiliales, ?int $profilFilialeId): array
    {
        $authUser = Auth::user();

        if ($authUser?->isSuperAdmin()) {
            $filialesToAttach = array_map('intval', $requestedFiliales);
            if ($profilFilialeId && ! in_array($profilFilialeId, $filialesToAttach, true)) {
                $filialesToAttach[] = $profilFilialeId;
            }

            return array_values(array_unique($filialesToAttach));
        }

        $filialeId = $profilFilialeId ?? $authUser?->primaryFilialeId();

        return $filialeId ? [$filialeId] : [];
    }

    /**
     * Rôles proposés dans les formulaires création / édition utilisateur.
     *
     * @return \Illuminate\Support\Collection<int, Role>
     */
    private function assignableRoles()
    {
        $query = Role::query()
            ->where('actif', true)
            ->orderBy('nom');

        $currentUser = Auth::user();
        if (! $currentUser?->isSuperAdmin()) {
            $query->where('slug', '!=', 'super_admin');
        }

        return $query->get(['id', 'nom', 'slug']);
    }

    /**
     * @return list<int>
     */
    private function roleIdsFromProfilDepartement(Profil $profil): array
    {
        $roleSlug = ProfilDepartementRoleResolver::resolve($profil->departement);
        if ($roleSlug === null) {
            return [];
        }

        $role = Role::query()
            ->where('slug', $roleSlug)
            ->where('actif', true)
            ->first();

        return $role ? [(int) $role->id] : [];
    }
}
