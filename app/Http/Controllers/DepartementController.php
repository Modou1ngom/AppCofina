<?php

namespace App\Http\Controllers;

use App\Models\Departement;
use App\Models\Profil;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 5);
        $departements = Departement::orderBy('nom')->paginate($perPage);
        
        // Compter le nombre de profils par département
        $departements->each(function ($departement) {
            $departement->profils_count = Profil::where('departement', $departement->nom)->count();
        });
        
        return Inertia::render('departements/Index', [
            'departements' => $departements,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('departements/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:departements,nom',
            'description' => 'nullable|string',
            'actif' => 'required|in:actif,inactif',
            // Informations du responsable
            'responsable_nom' => 'nullable|string|max:255',
            'responsable_prenom' => 'nullable|string|max:255',
            'responsable_email' => 'nullable|email|unique:profiles,email',
            'responsable_telephone' => ['nullable', 'string', 'max:20', 'regex:/^(\\+221|00221|221)?[0-9]{9}$/'],
            'responsable_fonction' => 'nullable|string|max:255',
        ]);

        $responsableId = null;

        // Créer le profil du responsable si les informations sont fournies
        if (!empty($validated['responsable_nom']) && !empty($validated['responsable_prenom'])) {
            $responsable = Profil::create([
                'nom' => $validated['responsable_nom'],
                'prenom' => $validated['responsable_prenom'],
                'matricule' => Profil::generateMatricule(),
                'fonction' => $validated['responsable_fonction'] ?? null,
                'departement' => $validated['nom'], // Le nom du département
                'email' => $validated['responsable_email'] ?? null,
                'telephone' => $validated['responsable_telephone'] ?? null,
                'statut' => 'actif',
            ]);

            $responsableId = $responsable->id;
        }

        Departement::create([
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'actif' => $validated['actif'] === 'actif',
            'responsable_departement_id' => $responsableId,
        ]);

        return redirect()->route('departements.index')
            ->with('success', 'Département créé avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(Departement $departement)
    {
        $departement->load('responsable');
        $profils = Profil::where('departement', $departement->nom)->get();
        
        return Inertia::render('departements/Show', [
            'departement' => $departement,
            'profils' => $profils,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Departement $departement)
    {
        $departement->load('responsable');
        
        return Inertia::render('departements/Edit', [
            'departement' => $departement,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Departement $departement)
    {
        $emailRule = 'nullable|email';
        if ($departement->responsable_departement_id) {
            $emailRule .= '|unique:profiles,email,' . $departement->responsable_departement_id;
        } else {
            $emailRule .= '|unique:profiles,email';
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:departements,nom,' . $departement->id,
            'description' => 'nullable|string',
            'actif' => 'required|in:actif,inactif',
            // Informations du responsable
            'responsable_nom' => 'nullable|string|max:255',
            'responsable_prenom' => 'nullable|string|max:255',
            'responsable_email' => $emailRule,
            'responsable_telephone' => ['nullable', 'string', 'max:20', 'regex:/^(\\+221|00221|221)?[0-9]{9}$/'],
            'responsable_fonction' => 'nullable|string|max:255',
        ]);

        $responsableId = $departement->responsable_departement_id;

        // Si un responsable existe déjà, le mettre à jour
        if ($responsableId && !empty($validated['responsable_nom']) && !empty($validated['responsable_prenom'])) {
            $responsable = Profil::find($responsableId);
            if ($responsable) {
                $responsable->update([
                    'nom' => $validated['responsable_nom'],
                    'prenom' => $validated['responsable_prenom'],
                    'fonction' => $validated['responsable_fonction'] ?? $responsable->fonction,
                    'departement' => $validated['nom'],
                    'email' => $validated['responsable_email'] ?? $responsable->email,
                    'telephone' => $validated['responsable_telephone'] ?? $responsable->telephone,
                ]);
            }
        } 
        // Sinon, créer un nouveau responsable si les informations sont fournies
        elseif (!empty($validated['responsable_nom']) && !empty($validated['responsable_prenom'])) {
            $responsable = Profil::create([
                'nom' => $validated['responsable_nom'],
                'prenom' => $validated['responsable_prenom'],
                'matricule' => Profil::generateMatricule(),
                'fonction' => $validated['responsable_fonction'] ?? null,
                'departement' => $validated['nom'],
                'email' => $validated['responsable_email'] ?? null,
                'telephone' => $validated['responsable_telephone'] ?? null,
                'statut' => 'actif',
            ]);

            $responsableId = $responsable->id;
        }

        $departement->update([
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
            'actif' => $validated['actif'] === 'actif',
            'responsable_departement_id' => $responsableId,
        ]);

        return redirect()->route('departements.index')
            ->with('success', 'Département mis à jour avec succès !');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Departement $departement)
    {
        $departement->delete();
        
        return redirect()->route('departements.index')
            ->with('success', 'Département supprimé avec succès !');
    }
}
