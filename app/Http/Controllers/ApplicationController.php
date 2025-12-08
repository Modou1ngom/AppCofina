<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $applications = Application::orderBy('ordre')
            ->orderBy('nom')
            ->paginate($request->get('per_page', 5));

        return Inertia::render('applications/Index', [
            'applications' => $applications,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('applications/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:applications,nom',
            'description' => 'nullable|string',
            'actif' => 'boolean',
            'ordre' => 'integer|min:0',
        ]);

        Application::create([
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'actif' => isset($validated['actif']) ? (bool) $validated['actif'] : true,
            'ordre' => $validated['ordre'] ?? 0,
        ]);

        return redirect()->route('applications.index')
            ->with('success', 'Application créée avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(Application $application)
    {
        return Inertia::render('applications/Show', [
            'application' => $application,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Application $application)
    {
        return Inertia::render('applications/Edit', [
            'application' => $application,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Application $application)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:applications,nom,' . $application->id,
            'description' => 'nullable|string',
            'actif' => 'boolean',
            'ordre' => 'integer|min:0',
        ]);

        $application->update($validated);

        // Pour les routes web, toujours retourner une redirection Inertia
        // Ne jamais retourner de JSON pour les routes web
        return redirect()->route('applications.index')
            ->with('success', 'Application mise à jour avec succès !');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Application $application)
    {
        $application->delete();

        // Pour les routes web, toujours retourner une redirection Inertia
        // Ne jamais retourner de JSON pour les routes web
        return redirect()->route('applications.index')
            ->with('success', 'Application supprimée avec succès !');
    }
}

