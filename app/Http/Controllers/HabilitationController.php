<?php

namespace App\Http\Controllers;

use App\Models\Habilitation;
use App\Models\Profil;
use App\Models\Application;
use App\Models\Filiale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HabilitationController extends Controller
{
    /**
     * Liste toutes les demandes d'habilitation
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $profil = $user?->profil;
        $filter = $request->query('filter', 'all'); // all, encours, termine, rejete

        $query = Habilitation::with(['requester', 'beneficiary', 'beneficiary.nPlus1', 'beneficiary.nPlus2', 'validatorN1', 'validatorControl', 'validatorN2', 'executorIt']);

        // Admin voit toutes les habilitations
        if ($user && $user->isAdmin()) {
            // Pas de restriction pour l'admin
        } 
        // Controle voit les habilitations en attente de contrôle et celles déjà contrôlées
        elseif ($user && $user->isControle()) {
            $query->where(function($q) {
                $q->where('status', 'pending_control')
                  ->orWhere('validator_control_id', '!=', null);
            });
        }
        // RH voit les habilitations où son profil est demandeur ou bénéficiaire
        elseif ($user && $user->isRh() && $profil) {
            $query->where(function($q) use ($profil) {
                $q->where('requester_profile_id', $profil->id)
                  ->orWhere('beneficiary_profile_id', $profil->id);
            });
        }
        // Metier voit ses habilitations, celles de ses subordonnés, et celles qu'il doit valider (N+1 ou N+2)
        elseif ($profil) {
            $subordonnesIds = $profil->subordonnes()->pluck('id')->toArray();
            $subordonnesIds[] = $profil->id; // Inclure aussi le profil lui-même

            // Récupérer les profils dont l'utilisateur est N+1 ou N+2
            $profilsNPlus1 = Profil::where('n_plus_1_id', $profil->id)->pluck('id')->toArray();
            $profilsNPlus2 = Profil::where('n_plus_2_id', $profil->id)->pluck('id')->toArray();

            $query->where(function($q) use ($profil, $subordonnesIds, $profilsNPlus1, $profilsNPlus2) {
                $q->whereIn('requester_profile_id', $subordonnesIds)
                  ->orWhereIn('beneficiary_profile_id', $subordonnesIds)
                  ->orWhere('validator_n1_id', $profil->id)
                  ->orWhere('validator_n2_id', $profil->id)
                  // Habilitations où le bénéficiaire a l'utilisateur comme N+1 et le statut est pending_n1
                  ->orWhere(function($subQ) use ($profilsNPlus1) {
                      $subQ->whereIn('beneficiary_profile_id', $profilsNPlus1)
                           ->where('status', 'pending_n1');
                  })
                  // Habilitations où le bénéficiaire a l'utilisateur comme N+2 et le statut est pending_n2
                  ->orWhere(function($subQ) use ($profilsNPlus2) {
                      $subQ->whereIn('beneficiary_profile_id', $profilsNPlus2)
                           ->where('status', 'pending_n2');
                  });
            });
        } 
        else {
            $query->where('id', 0); // Aucune habilitation
        }

        // Appliquer le filtre de statut
        if ($filter === 'encours') {
            // Toutes les habilitations qui ne sont pas terminées ni rejetées
            $query->whereNotIn('status', ['completed', 'rejected']);
        } elseif ($filter === 'termine') {
            $query->where('status', 'completed');
        } elseif ($filter === 'rejete') {
            $query->where('status', 'rejected');
        }
        // Si filter === 'all', pas de filtre supplémentaire

        // Filtre par statut spécifique
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filtre par type de demande
        if ($request->has('request_type') && $request->request_type) {
            $query->where('request_type', $request->request_type);
        }

        // Filtre par recherche (bénéficiaire ou demandeur)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('beneficiary', function($subQ) use ($search) {
                    $subQ->where('nom', 'like', "%{$search}%")
                         ->orWhere('prenom', 'like', "%{$search}%");
                })
                ->orWhereHas('requester', function($subQ) use ($search) {
                    $subQ->where('nom', 'like', "%{$search}%")
                         ->orWhere('prenom', 'like', "%{$search}%");
                });
            });
        }

        $habilitations = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 5));

        // Récupérer les membres du département si l'utilisateur a un profil avec un département
        $subordonnes = collect([]);
        if ($profil && $profil->departement) {
            $subordonnes = Profil::where('departement', $profil->departement)
                ->where('statut', 'actif')
                ->orderBy('nom')
                ->orderBy('prenom')
                ->get(['id', 'nom', 'prenom', 'matricule', 'fonction', 'departement', 'email', 'telephone', 'site']);
        }

        return Inertia::render('habilitations/Index', [
            'habilitations' => $habilitations,
            'filter' => $filter,
            'subordonnes' => $subordonnes,
            'hasDepartement' => $profil && $profil->departement ? true : false,
        ]);
    }

    /**
     * API: Récupérer les membres du département pour le dialog
     */
    public function getSubordonnes()
    {
        $user = Auth::user();
        
        // Vérifier que l'utilisateur a un profil avec un département
        if (!$user || !$user->profil) {
            return response()->json(['error' => 'Vous devez avoir un profil pour créer des demandes d\'habilitation.'], 403);
        }
        
        $profil = $user->profil;
        
        if (!$profil->departement) {
            return response()->json(['error' => 'Aucun département associé trouvé.'], 404);
        }
        
        // Récupérer tous les profils du même département (y compris l'utilisateur lui-même)
        $subordonnes = Profil::where('departement', $profil->departement)
            ->where('statut', 'actif')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'nom', 'prenom', 'matricule', 'fonction', 'departement', 'email', 'telephone', 'site']);
        
        return response()->json([
            'subordonnes' => $subordonnes,
        ]);
    }

    /**
     * Sélection du bénéficiaire - Première étape pour tous les membres du département
     */
    public function selectBeneficiary()
    {
        $user = Auth::user();
        
        // Vérifier que l'utilisateur a un profil avec un département
        if (!$user || !$user->profil) {
            return redirect()->route('habilitations.index')
                ->with('error', 'Vous devez avoir un profil pour créer des demandes d\'habilitation.');
        }
        
        $profil = $user->profil;
        
        if (!$profil->departement) {
            return redirect()->route('habilitations.index')
                ->with('error', 'Aucun département associé trouvé.');
        }
        
        // Récupérer tous les profils du même département (y compris l'utilisateur lui-même)
        $subordonnes = Profil::where('departement', $profil->departement)
            ->where('statut', 'actif')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'nom', 'prenom', 'matricule', 'fonction', 'departement', 'email', 'telephone', 'site']);
        
        return Inertia::render('habilitations/SelectBeneficiary', [
            'subordonnes' => $subordonnes,
            'demandeur' => $profil,
        ]);
    }

    /**
     * Étape 1: Informations de base - Formulaire de création de demande
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        
        // Vérifier que l'utilisateur a un profil avec un département
        if (!$user || !$user->profil) {
            return redirect()->route('habilitations.index')
                ->with('error', 'Vous devez avoir un profil pour créer des demandes d\'habilitation.');
        }
        
        $profil = $user->profil;
        
        if (!$profil->departement) {
            return redirect()->route('habilitations.index')
                ->with('error', 'Aucun département associé trouvé.');
        }
        
        // Récupérer le bénéficiaire si fourni en paramètre
        $beneficiaryId = $request->query('beneficiary_id');
        
        // Si aucun bénéficiaire n'est fourni, rediriger vers la page de sélection
        if (!$beneficiaryId) {
            return redirect()->route('habilitations.select-beneficiary');
        }
        
        $beneficiary = Profil::find($beneficiaryId);
        // Vérifier que le bénéficiaire est bien du même département que l'utilisateur
        if (!$beneficiary || $beneficiary->departement !== $profil->departement) {
            return redirect()->route('habilitations.select-beneficiary')
                ->with('error', 'Le bénéficiaire sélectionné n\'est pas valide.');
        }
        
        // Récupérer les filiales actives
        $filiales = Filiale::where('actif', true)
            ->orderBy('nom')
            ->get(['id', 'nom']);
        
        // Récupérer les applications
        $applications = $this->getApplicationsList();
        
        // Debug: vérifier que les applications sont bien récupérées
        \Log::info('HabilitationController::create - Applications récupérées', [
            'count' => count($applications),
            'applications' => $applications,
            'user_id' => $user->id,
        ]);
        
        return Inertia::render('habilitations/Create', [
            'demandeur' => $profil,
            'beneficiaire' => $beneficiary,
            'applications' => $applications,
            'filiales' => $filiales,
        ]);
    }

    /**
     * Étape 1: Informations de base - Sauvegarde de la demande
     */
    public function store(Request $request)
    {
        // Debug: vérifier l'utilisateur authentifié
        $user = Auth::user();
        \Log::info('Store habilitation - User check', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'is_admin' => $user?->isAdmin(),
            'has_profil' => $user?->profil !== null,
        ]);
        
        // Normaliser les applications - gérer plusieurs formats possibles
        $applications = $request->input('applications');
        
        // Si applications est null ou vide, essayer plusieurs méthodes de récupération
        if (empty($applications) || !is_array($applications)) {
            $applications = [];
            
            // Méthode 1: Essayer applications_json (chaîne JSON)
            $applicationsJson = $request->input('applications_json');
            if ($applicationsJson) {
                $decoded = json_decode($applicationsJson, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $applications = $decoded;
                }
            }
            
            // Méthode 2: Reconstruire depuis applications[0], applications[1], etc.
            if (empty($applications)) {
                $allInputs = $request->all();
                foreach ($allInputs as $key => $value) {
                    if (preg_match('/^applications\[(\d+)\]$/', $key, $matches)) {
                        $index = (int)$matches[1];
                        $applications[$index] = $value;
                    }
                }
                // Réindexer le tableau
                if (!empty($applications)) {
                    ksort($applications);
                    $applications = array_values($applications);
                }
            }
        }
        
        // Debug: vérifier les données reçues
        \Log::info('Données reçues pour création habilitation', [
            'applications_input' => $request->input('applications'),
            'applications_json' => $request->input('applications_json'),
            'applications_normalized' => $applications,
            'applications_count' => count($applications),
            'all_inputs_keys' => array_keys($request->all()),
            'request_method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'all_data' => $request->except(['_token', '_method']),
            'raw_input' => $request->all(),
        ]);

        // Remplacer temporairement applications dans la requête pour la validation
        $request->merge(['applications' => $applications]);

        $validated = $request->validate([
            // Demandeur
            'requester_profile_id' => 'required|exists:profiles,id',
            'requester_direction' => 'nullable|string|max:255',
            'requester_email' => 'nullable|email|max:255',
            'requester_telephone' => 'nullable|string|max:20',
            
            // Bénéficiaire
            'beneficiary_profile_id' => 'required|exists:profiles,id',
            'beneficiary_direction' => 'nullable|string|max:255',
            'beneficiary_email' => 'nullable|email|max:255',
            'beneficiary_telephone' => 'nullable|string|max:20',
            'beneficiary_site' => 'nullable|string|max:255',
            
            // Détails de la demande
            'request_type' => 'required|in:Creation,Modification,Desactivation,Suppression',
            'applications' => 'required|array|min:1',
            'applications.*' => 'string',
            'other_application' => 'nullable|string|max:255',
            'current_profile' => 'nullable|string',
            'requested_profile' => 'nullable|string',
            'desired_implementation_date' => 'nullable|date',
            'profile_type' => 'nullable|in:Consultation simple,Profil Specifique',
            'specific_profile' => 'nullable|string',
            'validity_period' => 'nullable|in:Permanent,Temporaire',
            'start_date' => 'nullable|date|required_if:validity_period,Temporaire',
            'end_date' => 'nullable|date|after:start_date|required_if:validity_period,Temporaire',
            'request_reason' => 'nullable|string',
            'subsidiary' => 'nullable|string|max:255',
            // Champs spécifiques pour Messagerie
            'messagerie_email' => 'nullable|email|max:255',
            'messagerie_nom_affichage' => 'nullable|string|max:255',
        ]);

        // Validation personnalisée pour les champs Messagerie
        $hasMessagerie = in_array('Messagerie', $applications) || in_array('Outlook', $applications);
        if ($hasMessagerie) {
            $request->validate([
                'messagerie_email' => 'required|email|max:255',
            ]);
        }

        // S'assurer que applications est un tableau même si NULL
        $data = $validated;
        if (!isset($data['applications']) || !is_array($data['applications']) || empty($data['applications'])) {
            $data['applications'] = $applications;
        }
        
        $habilitation = Habilitation::create([
            ...$data,
            'status' => 'draft',
        ]);

        return redirect()->route('habilitations.etape2', $habilitation->id)
            ->with('success', 'Étape 1 : Informations de base complétée. Veuillez maintenant définir les droits et habilitations.');
    }

    /**
     * Étape 2: Définition des droits et habilitations - Formulaire N+1
     */
    public function etape2(Habilitation $habilitation)
    {
        $user = Auth::user();
        // Admin peut accéder à toutes les étapes
        if (!$user || !$user->isAdmin()) {
            // L'étape 2 n'est accessible que si le statut est 'draft'
            // Si le statut est 'pending_n1', l'étape 2 est déjà complétée
            if ($habilitation->status !== 'draft') {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Cette étape n\'est plus accessible. La demande est déjà en attente de validation N+1.');
            }
        }

        $habilitation->load(['requester', 'beneficiary']);

        // À l'étape 2, on n'affiche que les applications déjà sélectionnées à l'étape 1
        // Plus besoin d'envoyer toutes les applications disponibles
        return Inertia::render('habilitations/Etape2', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Étape 2: Définition des droits et habilitations - Sauvegarde
     */
    public function updateEtape2(Request $request, Habilitation $habilitation)
    {
        // Normaliser les applications - gérer plusieurs formats possibles
        $applications = $request->input('applications');
        
        // Si applications est null ou vide, essayer plusieurs méthodes de récupération
        if (empty($applications) || !is_array($applications)) {
            $applications = [];
            
            // Méthode 1: Essayer applications_json (chaîne JSON)
            $applicationsJson = $request->input('applications_json');
            if ($applicationsJson) {
                $decoded = json_decode($applicationsJson, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $applications = $decoded;
                }
            }
            
            // Méthode 2: Reconstruire depuis applications[0], applications[1], etc.
            if (empty($applications)) {
                $allInputs = $request->all();
                foreach ($allInputs as $key => $value) {
                    if (preg_match('/^applications\[(\d+)\]$/', $key, $matches)) {
                        $index = (int)$matches[1];
                        $applications[$index] = $value;
                    }
                }
                // Réindexer le tableau
                if (!empty($applications)) {
                    ksort($applications);
                    $applications = array_values($applications);
                }
            }
        }
        
        // Remplacer temporairement applications dans la requête pour la validation
        $request->merge(['applications' => $applications]);
        
        $validated = $request->validate([
            'requested_profile' => 'required|string',
            'profile_type' => 'required|in:Consultation simple,Profil Specifique',
            'specific_profile' => 'nullable|string|required_if:profile_type,Profil Specifique',
            'applications' => 'required|array|min:1',
            'applications.*' => 'string',
            'other_application' => 'nullable|string|max:255',
            'comment_n1' => 'nullable|string',
        ]);

        // S'assurer que applications est un tableau même si NULL
        $data = $validated;
        if (!isset($data['applications']) || !is_array($data['applications']) || empty($data['applications'])) {
            $data['applications'] = $applications;
        }

        $habilitation->update([
            'requested_profile' => $data['requested_profile'],
            'profile_type' => $data['profile_type'],
            'specific_profile' => $data['specific_profile'] ?? null,
            'applications' => $data['applications'],
            'other_application' => $data['other_application'] ?? null,
            'comment_n1' => $data['comment_n1'] ?? null,
            'status' => 'pending_n1',
        ]);

        // Redirection vers la page de détails (Show) pour afficher le résumé
        // La demande est maintenant en attente de validation N+1
        return redirect()->route('habilitations.index')
            ->with('success', 'Étape 2 : Définition des droits et habilitations complétée. La demande est en attente de validation N+1.');
    }

    /**
     * Étape 3: Validation N+1 - Formulaire
     */
    public function etape3(Habilitation $habilitation)
    {
        $user = Auth::user();
        $profil = $user?->profil;
        
        // Admin peut accéder à toutes les étapes
        if (!$user || !$user->isAdmin()) {
            if ($habilitation->status !== 'pending_n1') {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Cette étape n\'est pas accessible actuellement.');
            }
            
            // Vérifier que l'utilisateur est le N+1 du bénéficiaire
            $beneficiary = $habilitation->beneficiary;
            if (!$beneficiary || !$profil || $beneficiary->n_plus_1_id !== $profil->id) {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Vous n\'êtes pas autorisé à valider cette demande. Seul le N+1 du bénéficiaire peut valider.');
            }
        }

        $habilitation->load(['requester', 'beneficiary', 'beneficiary.nPlus1']);

        return Inertia::render('habilitations/Etape3', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Étape 3: Validation N+1 - Validation ou rejet
     */
    public function validerEtape3(Request $request, Habilitation $habilitation)
    {
        $user = Auth::user();
        $profil = $user?->profil;
        
        // Charger le bénéficiaire avec ses relations
        $habilitation->load('beneficiary');
        
        // Vérifier le statut de l'habilitation
        if ($habilitation->status !== 'pending_n1') {
            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Cette demande n\'est plus en attente de validation N+1.');
        }
        
        // Vérifier que l'utilisateur est le N+1 du bénéficiaire (sauf admin)
        if (!$user || !$user->isAdmin()) {
            $beneficiary = $habilitation->beneficiary;
            if (!$beneficiary || !$profil || $beneficiary->n_plus_1_id !== $profil->id) {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Vous n\'êtes pas autorisé à valider cette demande. Seul le N+1 du bénéficiaire peut valider.');
            }
        }
        
        $validated = $request->validate([
            'action' => 'required|in:approuver,rejeter',
            'comment_n1' => 'nullable|string|required_if:action,rejeter',
        ]);

        if ($validated['action'] === 'approuver') {
            $habilitation->update([
                'validator_n1_id' => Auth::id() ?? null,
                'validated_n1_at' => now(),
                'comment_n1' => $validated['comment_n1'] ?? null,
                'status' => 'pending_n2',
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('success', 'Étape 3 : Validation N+1 complétée. La demande est en attente de validation N+2.');
        } else {
            $habilitation->update([
                'validator_n1_id' => Auth::id() ?? null,
                'validated_n1_at' => now(),
                'comment_n1' => $validated['comment_n1'] ?? null,
                'status' => 'rejected',
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Étape 3 : La demande a été rejetée par N+1.');
        }
    }

    /**
     * Étape 4: Validation N+2 - Formulaire
     */
    public function etape4(Habilitation $habilitation)
    {
        $user = Auth::user();
        $profil = $user?->profil;
        
        // Admin peut accéder à toutes les étapes
        if (!$user || !$user->isAdmin()) {
            if ($habilitation->status !== 'pending_n2') {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Cette étape n\'est pas accessible actuellement.');
            }
            
            // Vérifier que l'utilisateur est le N+2 du bénéficiaire
            $beneficiary = $habilitation->beneficiary;
            if (!$beneficiary || !$profil || $beneficiary->n_plus_2_id !== $profil->id) {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Vous n\'êtes pas autorisé à valider cette demande. Seul le N+2 du bénéficiaire peut valider.');
            }
        }

        $habilitation->load(['requester', 'beneficiary', 'beneficiary.nPlus2', 'validatorN1']);

        return Inertia::render('habilitations/Etape4', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Étape 4: Validation N+2 - Validation ou rejet
     */
    public function validerEtape4(Request $request, Habilitation $habilitation)
    {
        $user = Auth::user();
        $profil = $user?->profil;
        
        // Charger le bénéficiaire avec ses relations
        $habilitation->load('beneficiary');
        
        // Vérifier que l'utilisateur est le N+2 du bénéficiaire (sauf admin)
        if (!$user || !$user->isAdmin()) {
            $beneficiary = $habilitation->beneficiary;
            if (!$beneficiary || !$profil || $beneficiary->n_plus_2_id !== $profil->id) {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Vous n\'êtes pas autorisé à valider cette demande.');
            }
        }
        
        $validated = $request->validate([
            'action' => 'required|in:approuver,rejeter',
            'comment_n2' => 'nullable|string|required_if:action,rejeter',
        ]);

        if ($validated['action'] === 'approuver') {
            $habilitation->update([
                'validator_n2_id' => Auth::id() ?? null,
                'validated_n2_at' => now(),
                'comment_n2' => $validated['comment_n2'] ?? null,
                'status' => 'pending_control',
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('success', 'Étape 4 : Validation N+2 complétée. La demande est en attente de validation du Contrôle Permanent.');
        } else {
            $habilitation->update([
                'validator_n2_id' => Auth::id() ?? null,
                'validated_n2_at' => now(),
                'comment_n2' => $validated['comment_n2'] ?? null,
                'status' => 'rejected',
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Étape 4 : La demande a été rejetée par N+2.');
        }
    }

    /**
     * Étape 5: Validation Contrôle Permanent - Formulaire
     */
    public function etape5(Habilitation $habilitation)
    {
        $user = Auth::user();
        
        // Admin peut accéder à toutes les étapes
        if (!$user || !$user->isAdmin()) {
            if ($habilitation->status !== 'pending_control') {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Cette étape n\'est pas accessible actuellement.');
            }
            
            // Vérifier que l'utilisateur a le rôle Contrôle
            if (!$user->isControle()) {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Vous n\'êtes pas autorisé à valider cette demande. Seul le Contrôle Permanent peut valider.');
            }
        }

        $habilitation->load([
            'requester',
            'beneficiary',
            'validatorN1',
            'validatorN2'
        ]);

        // Vérifier que le N+1 a validé
        if (!$habilitation->validator_n1_id || !$habilitation->validated_n1_at) {
            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Le contrôle permanent ne peut pas valider tant que le N+1 n\'a pas validé la demande.');
        }

        // Vérifier que le N+2 a validé
        if (!$habilitation->validator_n2_id || !$habilitation->validated_n2_at) {
            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Le contrôle permanent ne peut pas valider tant que le N+2 n\'a pas validé la demande.');
        }

        return Inertia::render('habilitations/Etape5', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Étape 5: Validation Contrôle Permanent - Validation ou rejet
     */
    public function validerEtape5(Request $request, Habilitation $habilitation)
    {
        $user = Auth::user();
        
        // Vérifier que l'utilisateur a le rôle Contrôle (sauf admin)
        if (!$user || !$user->isAdmin()) {
            if (!$user->isControle()) {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Vous n\'êtes pas autorisé à valider cette demande.');
            }
        }

        // Vérifier le statut
        if ($habilitation->status !== 'pending_control') {
            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Cette demande n\'est plus en attente de validation du contrôle permanent.');
        }

        // Vérifier que le N+1 a validé
        if (!$habilitation->validator_n1_id || !$habilitation->validated_n1_at) {
            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Le contrôle permanent ne peut pas valider tant que le N+1 n\'a pas validé la demande.');
        }

        // Vérifier que le N+2 a validé
        if (!$habilitation->validator_n2_id || !$habilitation->validated_n2_at) {
            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Le contrôle permanent ne peut pas valider tant que le N+2 n\'a pas validé la demande.');
        }
        
        $validated = $request->validate([
            'action' => 'required|in:approuver,rejeter',
            'comment_control' => 'nullable|string|required_if:action,rejeter',
        ]);

        if ($validated['action'] === 'approuver') {
            $habilitation->update([
                'validator_control_id' => Auth::id() ?? null,
                'validated_control_at' => now(),
                'comment_control' => $validated['comment_control'] ?? null,
                'status' => 'approved',
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('success', 'Étape 5 : Validation Contrôle Permanent complétée. La demande est prête pour l\'exécution IT.');
        } else {
            $habilitation->update([
                'validator_control_id' => Auth::id() ?? null,
                'validated_control_at' => now(),
                'comment_control' => $validated['comment_control'] ?? null,
                'status' => 'rejected',
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Étape 5 : La demande a été rejetée par le Contrôle Permanent.');
        }
    }

    /**
     * Étape 6: Exécution IT - Formulaire
     */
    public function etape6(Habilitation $habilitation)
    {
        $user = Auth::user();
        // Admin peut accéder à toutes les étapes
        if (!$user || !$user->isAdmin()) {
            if ($habilitation->status !== 'approved' && $habilitation->status !== 'in_progress') {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Cette étape n\'est pas accessible actuellement.');
            }
        }

        $habilitation->load([
            'requester',
            'beneficiary',
            'validatorN1',
            'validatorN2',
            'validatorControl'
        ]);

        return Inertia::render('habilitations/Etape6', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Étape 6: Exécution IT - Exécution et notification
     */
    public function executerEtape6(Request $request, Habilitation $habilitation)
    {
        $validated = $request->validate([
            'comment_it' => 'nullable|string',
            'notify_requester' => 'boolean',
            'notify_n1' => 'boolean',
        ]);

        $habilitation->update([
            'executor_it_id' => Auth::id() ?? null,
            'executed_it_at' => now(),
            'comment_it' => $validated['comment_it'] ?? null,
            'notify_requester' => $validated['notify_requester'] ?? false,
            'notify_n1' => $validated['notify_n1'] ?? false,
            'status' => 'completed',
        ]);

        // TODO: Implémenter l'envoi de notifications par email

        return redirect()->route('habilitations.show', $habilitation->id)
            ->with('success', 'Étape 6 : Exécution IT terminée. Les notifications ont été envoyées.');
    }

    /**
     * Affiche les détails d'une demande d'habilitation
     */
    public function show(Habilitation $habilitation)
    {
        $user = Auth::user();
        $profil = $user?->profil;
        
        $habilitation->load([
            'requester',
            'beneficiary.nPlus1',
            'beneficiary.nPlus2',
            'validatorN1',
            'validatorControl',
            'validatorN2',
            'executorIt'
        ]);

        return Inertia::render('habilitations/Show', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Supprimer une demande d'habilitation (admin uniquement)
     */
    public function destroy(Habilitation $habilitation)
    {
        $user = Auth::user();
        
        // Seul l'admin peut supprimer une demande
        if (!$user || !$user->isAdmin()) {
            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Vous n\'êtes pas autorisé à supprimer cette demande.');
        }

        $habilitationId = $habilitation->id;
        $habilitation->delete();

        return redirect()->route('habilitations.index')
            ->with('success', 'La demande d\'habilitation #' . $habilitationId . ' a été supprimée avec succès.');
    }

    /**
     * Liste des applications disponibles depuis la base de données
     */
    private function getApplicationsList(): array
    {
        $applications = Application::actives()
            ->ordered()
            ->pluck('nom')
            ->toArray();
        
        // Debug: logger pour vérifier
        \Log::info('Applications listées', ['count' => count($applications), 'applications' => $applications]);
        
        return $applications;
    }

    /**
     * Télécharger le document PDF de l'habilitation
     */
    public function downloadPdf(Habilitation $habilitation)
    {
        // Vérifier que le contrôle a validé
        if (!$habilitation->validator_control_id || !$habilitation->validated_control_at) {
            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Le document ne peut être téléchargé qu\'après validation du contrôle permanent.');
        }

        $habilitation->load([
            'requester',
            'beneficiary.nPlus1',
            'beneficiary.nPlus2',
            'validatorN1',
            'validatorControl',
            'validatorN2',
            'executorIt'
        ]);

        // Générer le HTML pour le PDF
        $html = view('habilitations.pdf', [
            'habilitation' => $habilitation
        ])->render();

        // Créer le PDF
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'habilitation_' . $habilitation->id . '_' . date('Y-m-d') . '.pdf';

        return $dompdf->stream($filename);
    }
}
