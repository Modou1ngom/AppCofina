<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Profil;
use App\Models\Departement;
use App\Models\Agence;
use App\Models\Filiale;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfilController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = (int) $request->get('per_page', 5);
        
        // Construire la requête de base
        $query = Profil::query();
        
        // Admin et RH voient tous les profils
        if ($user && ($user->isAdmin() || $user->isRh())) {
            // Pas de restriction pour l'admin et RH
        } else {
            // Les autres voient uniquement leur propre profil et leurs subordonnés
            $profil = $user?->profil;
            if ($profil) {
                $query->where(function($q) use ($profil) {
                    $q->where('id', $profil->id)
                      ->orWhere('n_plus_1_id', $profil->id);
                });
            } else {
                $query->where('id', 0);
            }
        }

        // Filtre par statut
        if ($request->has('statut') && $request->statut !== '') {
            $query->where('statut', $request->statut);
        }

        // Filtre par département
        if ($request->has('departement') && $request->departement) {
            $departement = Departement::find($request->departement);
            if ($departement) {
                $query->where('departement', $departement->nom);
            }
        }

        // Filtre par fonction
        if ($request->has('fonction') && $request->fonction) {
            $query->where('fonction', 'like', "%{$request->fonction}%");
        }

        // Filtre par site/agence
        if ($request->has('site') && $request->site) {
            $agence = Agence::find($request->site);
            if ($agence) {
                $query->where('site', $agence->nom);
            }
        }

        // Filtre par type de contrat
        if ($request->has('type_contrat') && $request->type_contrat) {
            $query->where('type_contrat', $request->type_contrat);
        }

        // Filtre par recherche (nom, prénom, matricule, email)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        $profils = $query->orderBy('nom')
            ->orderBy('prenom')
            ->paginate($perPage);

        // Récupérer les données pour les filtres
        $departements = Departement::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        $agences = Agence::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        
        return Inertia::render('profils/Index', [
            'profils' => $profils,
            'departements' => $departements,
            'agences' => $agences,
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
        $agences = Agence::where('actif', true)->orderBy('nom')->get(['id', 'nom', 'filiale_id']);
        $filiales = Filiale::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        
        return Inertia::render('profils/Create', [
            'profils' => $profils,
            'departements' => $departements,
            'agences' => $agences,
            'filiales' => $filiales,
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
                'type_office' => 'nullable|in:Back Office,Front Office',
                'n_plus_1_id' => 'nullable|exists:profiles,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        }
        
        // Générer automatiquement le matricule
        $validated['matricule'] = Profil::generateMatricule();

        // Calculer automatiquement N+2 : le N+1 du N+1
        $nPlus2Id = null;
        if (!empty($validated['n_plus_1_id'])) {
            $nPlus1 = Profil::find($validated['n_plus_1_id']);
            if ($nPlus1 && $nPlus1->n_plus_1_id) {
                $nPlus2Id = $nPlus1->n_plus_1_id;
            }
        }

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
            'type_office' => $validated['type_office'] ?? null,
            'n_plus_1_id' => $validated['n_plus_1_id'] ?? null,
            'n_plus_2_id' => $nPlus2Id,
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
        $profil->load([
            'nPlus1:id,nom,prenom,matricule',
            'nPlus2:id,nom,prenom,matricule',
            'subordonnes:id,nom,prenom,matricule'
        ]);
        
        // Préparer les données avec les relations en snake_case pour le frontend
        $profilData = $profil->toArray();
        $profilData['n_plus_1'] = $profil->nPlus1 ? $profil->nPlus1->only(['id', 'nom', 'prenom', 'matricule']) : null;
        $profilData['n_plus_2'] = $profil->nPlus2 ? $profil->nPlus2->only(['id', 'nom', 'prenom', 'matricule']) : null;
        $profilData['subordonnes'] = $profil->subordonnes->map(function($sub) {
            return $sub->only(['id', 'nom', 'prenom', 'matricule']);
        })->toArray();
        
        return Inertia::render('profils/Show', [
            'profil' => $profilData,
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
        $agences = Agence::where('actif', true)->orderBy('nom')->get(['id', 'nom', 'filiale_id']);
        $filiales = Filiale::where('actif', true)->orderBy('nom')->get(['id', 'nom']);
        
        return Inertia::render('profils/Edit', [
            'profil' => $profil,
            'profils' => $profils,
            'departements' => $departements,
            'agences' => $agences,
            'filiales' => $filiales,
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
            'type_office' => 'nullable|in:Back Office,Front Office',
            'n_plus_1_id' => 'nullable|exists:profiles,id',
        ]);

        // Calculer automatiquement N+2 : le N+1 du N+1
        $nPlus2Id = null;
        if (!empty($validated['n_plus_1_id'])) {
            $nPlus1 = Profil::find($validated['n_plus_1_id']);
            if ($nPlus1 && $nPlus1->n_plus_1_id) {
                $nPlus2Id = $nPlus1->n_plus_1_id;
            }
        }
        
        $validated['n_plus_2_id'] = $nPlus2Id;

        // Vérifier si le N+1 a changé
        $nPlus1Changed = isset($validated['n_plus_1_id']) && $profil->n_plus_1_id != $validated['n_plus_1_id'];
        
        $profil->update($validated);

        // Si le N+1 a changé, recalculer les N+2 de tous les subordonnés
        if ($nPlus1Changed) {
            $subordonnes = Profil::where('n_plus_1_id', $profil->id)->get();
            foreach ($subordonnes as $subordonne) {
                $subordonneNPlus2Id = null;
                if ($profil->n_plus_1_id) {
                    $subordonneNPlus2Id = $profil->n_plus_1_id;
                }
                $subordonne->update(['n_plus_2_id' => $subordonneNPlus2Id]);
            }
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

    /**
     * Show the import form.
     */
    public function showImport()
    {
        return Inertia::render('profils/Import');
    }

    /**
     * Import profiles from Excel file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('file');
            $data = Excel::toArray([], $file);
            
            if (empty($data) || empty($data[0])) {
                return back()->withErrors(['file' => 'Le fichier Excel est vide.']);
            }

            $rows = $data[0];
            $header = array_shift($rows); // Première ligne = en-têtes
            
            // Normaliser les en-têtes (minuscules, sans espaces)
            $headerMap = [];
            foreach ($header as $index => $col) {
                $normalized = strtolower(trim($col));
                $headerMap[$normalized] = $index;
            }

            // Mapping des colonnes possibles
            $columnMapping = [
                'nom' => ['nom', 'name', 'lastname', 'last_name'],
                'prenom' => ['prenom', 'firstname', 'first_name', 'prénom'],
                'matricule' => ['matricule', 'mat', 'id', 'employee_id'],
                'email' => ['email', 'e-mail', 'mail'],
                'telephone' => ['telephone', 'tel', 'phone', 'téléphone', 'mobile'],
                'fonction' => ['fonction', 'function', 'poste', 'job', 'position'],
                'departement' => ['departement', 'department', 'département', 'dept'],
                'site' => ['site', 'agence', 'agency', 'location'],
                'type_contrat' => ['type_contrat', 'type contrat', 'contract_type', 'contrat'],
                'statut' => ['statut', 'status', 'etat', 'état'],
                'type_office' => ['type_office', 'type office', 'back front office', 'back/front office', 'office', 'back office', 'front office'],
            ];

            $mappedColumns = [];
            foreach ($columnMapping as $dbColumn => $possibleNames) {
                foreach ($possibleNames as $name) {
                    if (isset($headerMap[$name])) {
                        $mappedColumns[$dbColumn] = $headerMap[$name];
                        break;
                    }
                }
            }

            // Vérifier que les colonnes obligatoires sont présentes
            if (!isset($mappedColumns['nom']) || !isset($mappedColumns['prenom'])) {
                return back()->withErrors(['file' => 'Le fichier doit contenir au moins les colonnes "Nom" et "Prénom".']);
            }

            $imported = 0;
            $skipped = 0;
            $errors = [];

            DB::beginTransaction();

            try {
                foreach ($rows as $rowIndex => $row) {
                    // Ignorer les lignes vides
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    $nom = trim($row[$mappedColumns['nom']] ?? '');
                    $prenom = trim($row[$mappedColumns['prenom'] ?? ''] ?? '');

                    // Ignorer si nom ou prénom est vide
                    if (empty($nom) || empty($prenom)) {
                        $skipped++;
                        continue;
                    }

                    // Récupérer les valeurs
                    $matricule = isset($mappedColumns['matricule']) ? trim($row[$mappedColumns['matricule']] ?? '') : null;
                    $email = isset($mappedColumns['email']) ? trim($row[$mappedColumns['email']] ?? '') : null;
                    $telephone = isset($mappedColumns['telephone']) ? trim($row[$mappedColumns['telephone']] ?? '') : null;
                    $fonction = isset($mappedColumns['fonction']) ? trim($row[$mappedColumns['fonction']] ?? '') : null;
                    $departement = isset($mappedColumns['departement']) ? trim($row[$mappedColumns['departement']] ?? '') : null;
                    $site = isset($mappedColumns['site']) ? trim($row[$mappedColumns['site']] ?? '') : null;
                    $typeContrat = isset($mappedColumns['type_contrat']) ? trim($row[$mappedColumns['type_contrat']] ?? '') : 'CDI';
                    $statut = isset($mappedColumns['statut']) ? trim($row[$mappedColumns['statut']] ?? '') : 'actif';
                    $typeOffice = isset($mappedColumns['type_office']) ? trim($row[$mappedColumns['type_office']] ?? '') : null;

                    // Valider le type de contrat
                    if (!in_array($typeContrat, ['CDI', 'CDD', 'Stagiaire', 'Autre'])) {
                        $typeContrat = 'CDI';
                    }

                    // Valider le statut
                    if (!in_array(strtolower($statut), ['actif', 'inactif'])) {
                        $statut = 'actif';
                    } else {
                        $statut = strtolower($statut);
                    }

                    // Valider le type_office
                    if ($typeOffice) {
                        $typeOfficeNormalized = trim($typeOffice);
                        // Normaliser les variations possibles
                        if (stripos($typeOfficeNormalized, 'back') !== false && stripos($typeOfficeNormalized, 'office') !== false) {
                            $typeOfficeNormalized = 'Back Office';
                        } elseif (stripos($typeOfficeNormalized, 'front') !== false && stripos($typeOfficeNormalized, 'office') !== false) {
                            $typeOfficeNormalized = 'Front Office';
                        } else {
                            // Si ce n'est pas une valeur valide, mettre à null
                            if (!in_array($typeOfficeNormalized, ['Back Office', 'Front Office'])) {
                                $typeOfficeNormalized = null;
                            }
                        }
                        $typeOffice = $typeOfficeNormalized;
                    } else {
                        $typeOffice = null;
                    }

                    // Gérer le matricule : utiliser celui du fichier s'il existe, sinon générer automatiquement
                    if (empty($matricule)) {
                        // Générer le matricule automatiquement si absent
                        $matricule = Profil::generateMatricule();
                    } else {
                        // Vérifier si le matricule existe déjà dans la base de données
                        if (Profil::where('matricule', $matricule)->exists()) {
                            $skipped++;
                            $errors[] = "Ligne " . ($rowIndex + 2) . ": Matricule déjà existant ($matricule)";
                            continue;
                        }
                    }

                    // Vérifier si l'email existe déjà (si fourni)
                    if ($email && Profil::where('email', $email)->exists()) {
                        $skipped++;
                        $errors[] = "Ligne " . ($rowIndex + 2) . ": Email déjà existant ($email)";
                        continue;
                    }

                    // Créer le profil
                    Profil::create([
                        'nom' => $nom,
                        'prenom' => $prenom,
                        'matricule' => $matricule,
                        'email' => $email ?: null,
                        'telephone' => $telephone ?: null,
                        'fonction' => $fonction ?: null,
                        'departement' => $departement ?: null,
                        'site' => $site ?: null,
                        'type_contrat' => $typeContrat,
                        'statut' => $statut,
                        'type_office' => $typeOffice,
                    ]);

                    $imported++;
                }

                DB::commit();

                $message = "$imported profil(s) importé(s) avec succès.";
                if ($skipped > 0) {
                    $message .= " $skipped ligne(s) ignorée(s).";
                }
                if (!empty($errors)) {
                    $message .= "\n\nErreurs rencontrées:\n" . implode("\n", $errors);
                }

                return redirect()->route('profils.index')
                    ->with('success', $message)
                    ->with('import_errors', $errors);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Erreur lors de l\'import Excel: ' . $e->getMessage());
                return back()->withErrors(['file' => 'Erreur lors de l\'import: ' . $e->getMessage()]);
            }

        } catch (\Exception $e) {
            Log::error('Erreur lors de la lecture du fichier Excel: ' . $e->getMessage());
            return back()->withErrors(['file' => 'Erreur lors de la lecture du fichier: ' . $e->getMessage()]);
        }
    }
}
