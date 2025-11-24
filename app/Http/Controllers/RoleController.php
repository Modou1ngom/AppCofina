<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::orderBy('nom')->get();
        
        return Inertia::render('profils/Roles/Index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('profils/Roles/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:roles,nom',
            'description' => 'nullable|string',
            'actif' => 'boolean',
        ]);

        $role = Role::create([
            'nom' => $validated['nom'],
            'slug' => Str::slug($validated['nom']),
            'description' => $validated['description'] ?? null,
            'actif' => $validated['actif'] ?? true,
        ]);

        return redirect()->route('roles.index')
            ->with('success', 'Rôle créé avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        $role->load('profils');
        
        return Inertia::render('profils/Roles/Show', [
            'role' => $role,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        return Inertia::render('profils/Roles/Edit', [
            'role' => $role,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:roles,nom,' . $role->id,
            'description' => 'nullable|string',
            'actif' => 'boolean',
        ]);

        $role->update([
            'nom' => $validated['nom'],
            'slug' => Str::slug($validated['nom']),
            'description' => $validated['description'] ?? null,
            'actif' => $validated['actif'] ?? true,
        ]);

        return redirect()->route('roles.index')
            ->with('success', 'Rôle mis à jour avec succès !');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        $role->delete();
        
        return redirect()->route('roles.index')
            ->with('success', 'Rôle supprimé avec succès !');
    }
}
