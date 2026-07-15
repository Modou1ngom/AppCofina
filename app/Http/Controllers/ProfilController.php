<?php

namespace App\Http\Controllers;

use App\Exports\ProfilsExport;
use App\Models\Agence;
use App\Models\Departement;
use App\Models\Filiale;
use App\Models\Profil;
use App\Models\User;
use App\Services\ProfilBulkImportService;
use App\Services\ProfilSignatureService;
use App\Services\ProfilUserProvisioningService;
use App\Support\ProfilExcelImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ProfilController extends Controller
{
    /**
     * Applique le filtrage par filiale selon le rôle de l'utilisateur
     */
    private function applyFilialeFilter($query, $user)
    {
        if ($user) {
            $user->applyProfilVisibilityScope($query);
            } else {
            $query->whereRaw('0 = 1');
        }

        return $query;
    }

    /**
     * Applique le filtrage des agences par filiale selon le rôle de l'utilisateur
     */
    private function filterAgencesByFiliale($user)
    {
        $query = Agence::where('actif', true);

        if ($user) {
            $user->applyFilialeScopeToQuery($query);
            } else {
            $query->whereRaw('0 = 1');
        }

        return $query;
    }

    /**
     * Vérifie si l'utilisateur peut accéder à un profil donné
     */
    private function canAccessProfil(Profil $profil, $user)
    {
        return $user && $user->canAccessProfil($profil);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = (int) $request->get('per_page', 5);

        // Construire la requête de base
        $query = Profil::query();

        if ($user) {
            $user->applyProfilVisibilityScope($query);
            } else {
            $query->whereRaw('0 = 1');
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
            $query->where(function ($q) use ($search) {
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
        $agencesQuery = $this->filterAgencesByFiliale($user);
        $agences = $agencesQuery->orderBy('nom')->get(['id', 'nom']);

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
        $user = Auth::user();
        $profilsQuery = Profil::query();
        $profilsQuery = $this->applyFilialeFilter($profilsQuery, $user);
        $profils = $profilsQuery->orderBy('nom')->get(['id', 'nom', 'prenom', 'matricule']);
        $departements = Departement::where('actif', true)
            ->with('responsable:id,nom,prenom,matricule')
            ->orderBy('nom')
            ->get(['id', 'nom', 'responsable_departement_id']);
        $agencesQuery = $this->filterAgencesByFiliale($user);
        $agences = $agencesQuery->orderBy('nom')->get(['id', 'nom', 'filiale_id']);
        $filiales = Filiale::where('actif', true)->orderBy('nom')->get(['id', 'nom']);

        // Déterminer la filiale de l'utilisateur pour l'assignation automatique
        $userFilialeId = null;
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $isAdmin = $user && $user->isAdmin();
        $isRh = $user && $user->isRh();

        // Pour les admins et RH, utiliser leur filiale assignée ou celle de leur profil
        if (($isAdmin || $isRh) && $user && ! $isSuperAdmin) {
            $userFiliales = $user->filiales()->get();
            if ($userFiliales->count() > 0) {
                // Prendre la première filiale assignée
                $userFilialeId = $userFiliales->first()->id;
            } elseif ($user->profil && $user->profil->filiale_id) {
                // Sinon, utiliser la filiale du profil
                $userFilialeId = $user->profil->filiale_id;
            }
        } elseif ($user && $user->profil && $user->profil->filiale_id) {
            // Pour les autres utilisateurs, utiliser la filiale de leur profil
            $userFilialeId = $user->profil->filiale_id;
        }

        return Inertia::render('profils/Create', [
            'profils' => $profils,
            'departements' => $departements,
            'agences' => $agences,
            'filiales' => $filiales,
            'userFilialeId' => $userFilialeId,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        try {
            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'fonction' => 'nullable|string',
                'departement' => 'nullable|string',
                'email' => ['nullable', 'email', 'unique:profiles,email', 'unique:users,email'],
                'telephone' => ['nullable', 'string', 'max:20', 'regex:/^(\\+221|00221|221)?[0-9]{9}$/'],
                'site' => 'nullable|string|max:100',
                'numero_compte' => 'nullable|string|max:255',
                'code_agence' => 'nullable|string|max:255',
                'filiale_id' => 'nullable|integer|exists:filiales,id',
                'type_contrat' => 'nullable|in:CDI,CDD,Stagiaire,Autre',
                'statut' => 'nullable|in:actif,inactif',
                'statut_rh' => 'nullable|string|max:255',
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
        if (! empty($validated['n_plus_1_id'])) {
            $nPlus1 = Profil::find($validated['n_plus_1_id']);
            // Ne pas permettre que le N+2 soit le même que le N+1 (éviter les boucles)
            if ($nPlus1 && $nPlus1->n_plus_1_id && $nPlus1->n_plus_1_id != $validated['n_plus_1_id']) {
                $nPlus2Id = $nPlus1->n_plus_1_id;
            }
        }

        // Déterminer la filiale à assigner
        $filialeId = $validated['filiale_id'] ?? null;

        // Si filiale_id n'est pas fourni, essayer de le déduire
        if (! $filialeId) {
            // 1. Essayer depuis le site/agence sélectionné
            if (! empty($validated['site'])) {
                $agence = Agence::where('nom', $validated['site'])->first();
                if ($agence && $agence->filiale_id) {
                    $filialeId = $agence->filiale_id;
                }
            }

            // 2. Pour les admins et RH, utiliser leur filiale assignée
            $isSuperAdmin = $user && $user->isSuperAdmin();
            $isAdmin = $user && $user->isAdmin();
            $isRh = $user && $user->isRh();

            if (! $filialeId && ($isAdmin || $isRh) && ! $isSuperAdmin) {
                $userFiliales = $user->filiales()->get();
                if ($userFiliales->count() > 0) {
                    // Prendre la première filiale assignée
                    $filialeId = $userFiliales->first()->id;
                } elseif ($user->profil && $user->profil->filiale_id) {
                    // Sinon, utiliser la filiale du profil
                    $filialeId = $user->profil->filiale_id;
                }
            }

            // 3. Pour les autres utilisateurs, utiliser la filiale de leur profil
            if (! $filialeId && $user && $user->profil && $user->profil->filiale_id) {
                $filialeId = $user->profil->filiale_id;
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
            'numero_compte' => $validated['numero_compte'] ?? null,
            'code_agence' => $validated['code_agence'] ?? null,
            'filiale_id' => $filialeId,
            'type_contrat' => $validated['type_contrat'] ?? null,
            'statut' => $validated['statut'] ?? 'actif',
            'statut_rh' => $validated['statut_rh'] ?? null,
            'type_office' => $validated['type_office'] ?? null,
            'n_plus_1_id' => $validated['n_plus_1_id'] ?? null,
            'n_plus_2_id' => $nPlus2Id,
        ];

        $provisioner = app(ProfilUserProvisioningService::class);

        $hadEmail = trim((string) ($data['email'] ?? '')) !== '';

        $createdUser = DB::transaction(function () use ($data, $provisioner) {
            $profil = Profil::create($data);

            return $provisioner->provisionUserForProfil($profil);
        });

        $message = 'Profil créé avec succès !';
        if (! $hadEmail) {
            $message .= ' Aucun compte utilisateur : renseignez un e-mail pour activer la connexion.';
        } elseif ($createdUser?->wasRecentlyCreated) {
            $message .= ' Un compte utilisateur a été créé avec le mot de passe par défaut (changement obligatoire à la première connexion).';
        } elseif ($createdUser) {
            $message .= ' Compte utilisateur existant associé à cet e-mail.';
        }

        return redirect()->route('profils.index')
            ->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(Profil $profil)
    {
        $user = Auth::user();

        // Vérifier l'accès : super admin peut voir tout, sinon vérifier les filiales
        if (! $this->canAccessProfil($profil, $user)) {
            abort(403, 'Vous n\'avez pas accès à ce profil.');
        }

        $profil->load([
            'nPlus1:id,nom,prenom,matricule',
            'nPlus2:id,nom,prenom,matricule',
            'subordonnes:id,nom,prenom,matricule',
        ]);

        // Préparer les données avec les relations en snake_case pour le frontend
        $profilData = $profil->toArray();
        $profilData['n_plus_1'] = $profil->nPlus1 ? $profil->nPlus1->only(['id', 'nom', 'prenom', 'matricule']) : null;
        $profilData['n_plus_2'] = $profil->nPlus2 ? $profil->nPlus2->only(['id', 'nom', 'prenom', 'matricule']) : null;
        $profilData['subordonnes'] = $profil->subordonnes->map(function ($sub) {
            return $sub->only(['id', 'nom', 'prenom', 'matricule']);
        })->toArray();

        $profilData['email'] = $profil->email;
        $profilData['compte_utilisateur'] = $this->compteUtilisateurPourProfil($profil);

        return Inertia::render('profils/Show', [
            'profil' => $profilData,
        ]);
    }

    /**
     * Compte User lié au profil (même e-mail).
     *
     * @return array{id: int, email: string, name: string}|null
     */
    private function compteUtilisateurPourProfil(Profil $profil): ?array
    {
        $email = strtolower(trim((string) $profil->email));
        if ($email === '') {
            return null;
        }

        $user = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first(['id', 'email', 'name']);

        if ($user === null) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ];
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Profil $profil)
    {
        $user = Auth::user();

        // Vérifier l'accès : super admin peut voir tout, sinon vérifier les filiales
        if (! $this->canAccessProfil($profil, $user)) {
            abort(403, 'Vous n\'avez pas accès à ce profil.');
        }

        $profilsQuery = Profil::where('id', '!=', $profil->id);
        $profilsQuery = $this->applyFilialeFilter($profilsQuery, $user);
        $profils = $profilsQuery->orderBy('nom')
            ->get(['id', 'nom', 'prenom', 'matricule']);
        $departements = Departement::where('actif', true)
            ->with('responsable:id,nom,prenom,matricule')
            ->orderBy('nom')
            ->get(['id', 'nom', 'responsable_departement_id']);
        $agencesQuery = $this->filterAgencesByFiliale($user);
        $agences = $agencesQuery->orderBy('nom')->get(['id', 'nom', 'filiale_id']);
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
        $user = Auth::user();

        // Vérifier l'accès : super admin peut modifier tout, sinon vérifier les filiales
        if (! $this->canAccessProfil($profil, $user)) {
            abort(403, 'Vous n\'avez pas accès à ce profil.');
        }

        $linkedUserId = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim((string) $request->input('email', $profil->email ?? '')))])
            ->value('id');

        $validated = $request->validate([
            'nom' => 'sometimes|required|string|max:255',
            'prenom' => 'sometimes|required|string|max:255',
            'matricule' => 'sometimes|required|string|max:50|unique:profiles,matricule,'.$profil->id,
            'fonction' => 'nullable|string',
            'departement' => 'nullable|string',
            'email' => [
                'nullable',
                'email',
                Rule::unique('profiles', 'email')->ignore($profil->id),
                Rule::unique('users', 'email')->ignore($linkedUserId),
            ],
            'telephone' => ['nullable', 'string', 'max:20', 'regex:/^(\\+221|00221|221)?[0-9]{9}$/'],
            'site' => 'nullable|string|max:100',
            'numero_compte' => 'nullable|string|max:255',
            'code_agence' => 'nullable|string|max:255',
            'type_contrat' => 'nullable|in:CDI,CDD,Stagiaire,Autre',
            'statut' => 'nullable|in:actif,inactif',
            'statut_rh' => 'nullable|string|max:255',
            'type_office' => 'nullable|in:Back Office,Front Office',
            'n_plus_1_id' => 'nullable|exists:profiles,id',
            'signature' => 'nullable|string',
            'replace_signature' => 'nullable|boolean',
            'signature_file' => 'nullable|image|max:2048',
        ]);

        // Calculer automatiquement N+2 : le N+1 du N+1
        $nPlus2Id = null;
        if (! empty($validated['n_plus_1_id'])) {
            // Ne pas permettre qu'un profil soit son propre N+1
            if ($validated['n_plus_1_id'] != $profil->id) {
                $nPlus1 = Profil::find($validated['n_plus_1_id']);
                // Ne pas permettre que le N+2 soit le même que le N+1
                if ($nPlus1 && $nPlus1->n_plus_1_id && $nPlus1->n_plus_1_id != $validated['n_plus_1_id']) {
                    $nPlus2Id = $nPlus1->n_plus_1_id;
                }
            }
        }

        $validated['n_plus_2_id'] = $nPlus2Id;

        $signaturePayload = $validated['signature'] ?? null;
        $replaceSignature = (bool) ($validated['replace_signature'] ?? false);
        unset($validated['signature'], $validated['replace_signature'], $validated['signature_file']);

        // Vérifier si le N+1 a changé
        $nPlus1Changed = isset($validated['n_plus_1_id']) && $profil->n_plus_1_id != $validated['n_plus_1_id'];

        $profil->update($validated);
        $profil->refresh();

        $signatureService = app(ProfilSignatureService::class);
        if ($request->hasFile('signature_file')) {
            $signatureService->storeFromUpload($profil, $request->file('signature_file'), true);
        } elseif ($signaturePayload) {
            $signatureService->attachToProfilAfterFirstSignature(
                $profil,
                $signaturePayload,
                $replaceSignature
            );
        }

        app(ProfilUserProvisioningService::class)->provisionUserForProfil($profil);

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
        $user = Auth::user();

        // Vérifier l'accès : super admin peut supprimer tout, sinon vérifier les filiales
        if (! $this->canAccessProfil($profil, $user)) {
            abort(403, 'Vous n\'avez pas accès à ce profil.');
        }

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
        $user = Auth::user();

        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240',
        ]);

        set_time_limit(600);

        try {
            $file = $request->file('file');
            $allRows = ProfilExcelImport::readRowsFromPath($file->getRealPath());

            if ($allRows === []) {
                return back()->withErrors(['file' => 'Le fichier Excel est vide.']);
            }

            $headerIndex = ProfilExcelImport::detectHeaderRowIndex($allRows);
            $header = $allRows[$headerIndex];
            $rows = array_slice($allRows, $headerIndex + 1);
            $mappedColumns = ProfilExcelImport::mapColumns($header);
            $mappedColumns = ProfilExcelImport::refineEmailColumnMapping($mappedColumns, $header, $rows);

            // Vérifier que les colonnes obligatoires sont présentes
            if (! isset($mappedColumns['nom']) || ! isset($mappedColumns['prenom'])) {
                return back()->withErrors(['file' => 'Le fichier doit contenir au moins les colonnes "Nom" et "Prénom".']);
            }

            $importWarnings = [];
            if (! isset($mappedColumns['email'])) {
                $importWarnings[] = 'Aucune colonne « Email » détectée dans les en-têtes (recherche sur chaque ligne).';
                    } else {
                $headerLabel = ProfilExcelImport::cellToString($header[$mappedColumns['email']] ?? '');
                if ($headerLabel !== '') {
                    $importWarnings[] = "Colonne e-mail utilisée : « {$headerLabel} ».";
                }
            }

            DB::beginTransaction();

            try {
                $result = app(ProfilBulkImportService::class)->process($rows, $mappedColumns, $headerIndex, $user);

                $imported = $result['imported'];
                $importedWithEmail = $result['imported_with_email'];
                $skipped = $result['skipped'];
                $errors = array_merge($importWarnings, $result['errors']);

                $usersProvisioned = 0;
                if (config('cofina.provision_user_on_profil_create', true) && $result['created_profils'] !== []) {
                    $usersProvisioned = app(ProfilUserProvisioningService::class)
                        ->provisionMany($result['created_profils']);
                }

                DB::commit();

                $emailsFromFile = $result['emails_from_file'];
                $emailsGenerated = $result['emails_generated'];
                $updated = $result['updated'];
                $created = $result['created'];
                $message = "{$imported} profil(s) traité(s) ({$created} créé(s), {$updated} mis à jour)";
                $message .= " — {$importedWithEmail} avec e-mail ({$emailsFromFile} depuis le fichier";
                if ($emailsGenerated > 0) {
                    $message .= ", {$emailsGenerated} généré(s)";
                }
                $message .= ').';
                if ($skipped > 0) {
                    $message .= " {$skipped} ligne(s) ignorée(s).";
                }
                if ($usersProvisioned > 0) {
                    $message .= " {$usersProvisioned} compte(s) utilisateur créé(s).";
                } elseif ($imported > 0 && config('cofina.provision_user_on_profil_create', true)) {
                    $message .= ' Aucun compte utilisateur créé (profils sans e-mail valide).';
                }
                if ($errors !== []) {
                    $message .= "\n\nErreurs / avertissements:\n".implode("\n", array_slice($errors, 0, 50));
                    if (count($errors) > 50) {
                        $message .= "\n... et ".(count($errors) - 50).' autre(s).';
                    }
                }

                return redirect()->route('profils.index')
                    ->with('success', $message)
                    ->with('import_errors', $errors);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Erreur lors de l\'import Excel: '.$e->getMessage());

                return back()->withErrors(['file' => 'Erreur lors de l\'import: '.$e->getMessage()]);
            }

        } catch (\Exception $e) {
            Log::error('Erreur lors de la lecture du fichier Excel: '.$e->getMessage());

            return back()->withErrors(['file' => 'Erreur lors de la lecture du fichier: '.$e->getMessage()]);
        }
    }

    /**
     * Export profiles to Excel file.
     */
    public function export(Request $request)
    {
        $user = Auth::user();

        // Construire la requête de base (même logique que index)
        $query = Profil::query();

        if ($user) {
            $user->applyProfilVisibilityScope($query);
        } else {
            $query->whereRaw('0 = 1');
        }

        // Appliquer les mêmes filtres que dans index
        if ($request->has('statut') && $request->statut !== '') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('departement') && $request->departement) {
            $departement = Departement::find($request->departement);
            if ($departement) {
                $query->where('departement', $departement->nom);
            }
        }

        if ($request->has('fonction') && $request->fonction) {
            $query->where('fonction', 'like', "%{$request->fonction}%");
        }

        if ($request->has('site') && $request->site) {
            $agence = Agence::find($request->site);
            if ($agence) {
                $query->where('site', $agence->nom);
            }
        }

        if ($request->has('type_contrat') && $request->type_contrat) {
            $query->where('type_contrat', $request->type_contrat);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Trier par nom puis prénom
        $query->orderBy('nom')->orderBy('prenom');

        $fileName = 'profils_'.date('Y-m-d_His').'.xlsx';

        return Excel::download(new ProfilsExport($query), $fileName);
    }
}
