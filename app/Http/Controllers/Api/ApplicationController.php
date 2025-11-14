<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    /**
     * Liste toutes les applications (actives par défaut)
     */
    public function index(Request $request)
    {
        $query = Application::query();

        // Filtrer par statut actif si demandé
        if ($request->has('actif')) {
            $actif = filter_var($request->input('actif'), FILTER_VALIDATE_BOOLEAN);
            $query->where('actif', $actif);
        }

        // Trier par ordre puis par nom
        $applications = $query->ordered()->get();

        return response()->json($applications);
    }

    /**
     * Crée une nouvelle application
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:applications,nom',
            'description' => 'nullable|string',
            'actif' => 'boolean',
            'ordre' => 'integer|min:0',
        ]);

        $application = Application::create([
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'actif' => $validated['actif'] ?? true,
            'ordre' => $validated['ordre'] ?? 0,
        ]);

        return response()->json($application, 201);
    }

    /**
     * Affiche une application spécifique
     */
    public function show(Application $application)
    {
        return response()->json($application);
    }

    /**
     * Met à jour une application
     */
    public function update(Request $request, Application $application)
    {
        $validated = $request->validate([
            'nom' => 'sometimes|required|string|max:255|unique:applications,nom,' . $application->id,
            'description' => 'nullable|string',
            'actif' => 'boolean',
            'ordre' => 'integer|min:0',
        ]);

        $application->update($validated);

        return response()->json($application);
    }

    /**
     * Supprime une application
     */
    public function destroy(Application $application)
    {
        $application->delete();

        return response()->json([
            'message' => 'Application supprimée avec succès'
        ]);
    }
}
