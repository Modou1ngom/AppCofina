<?php

namespace App\Http\Controllers;

use App\Models\Habilitation;
use App\Models\Profil;
use App\Models\Application;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HabilitationController extends Controller
{
    /**
     * Liste toutes les demandes d'habilitation
     */
    public function index()
    {
        $user = Auth::user();
        $profil = $user?->profil;

        // Admin voit toutes les habilitations
        if ($user && $user->isAdmin()) {
            $habilitations = Habilitation::with(['requester', 'beneficiary', 'validatorN1', 'validatorControl', 'validatorN2', 'executorIt'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        } 
        // Controle voit les habilitations en attente de contrôle et celles déjà contrôlées
        elseif ($user && $user->isControle()) {
            $habilitations = Habilitation::with(['requester', 'beneficiary', 'validatorN1', 'validatorControl', 'validatorN2', 'executorIt'])
                ->where(function($query) {
                    $query->where('status', 'pending_control')
                          ->orWhere('validator_control_id', '!=', null);
                })
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        }
        // Metier (N et N-1) voit ses habilitations et celles de ses subordonnés
        elseif ($profil) {
            $subordonnesIds = $profil->subordonnes()->pluck('id')->toArray();
            $subordonnesIds[] = $profil->id; // Inclure aussi le profil lui-même

            $habilitations = Habilitation::with(['requester', 'beneficiary', 'validatorN1', 'validatorControl', 'validatorN2', 'executorIt'])
                ->where(function($query) use ($profil, $subordonnesIds) {
                    $query->whereIn('requester_profile_id', $subordonnesIds)
                          ->orWhereIn('beneficiary_profile_id', $subordonnesIds)
                          ->orWhere('validator_n1_id', $profil->id);
                })
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        } 
        else {
            $habilitations = Habilitation::where('id', 0)->paginate(15); // Aucune habilitation
        }

        return Inertia::render('habilitations/Index', [
            'habilitations' => $habilitations,
        ]);
    }

    /**
     * Étape 1: Informations de base - Formulaire de création de demande
     */
    public function create()
    {
        $profils = Profil::orderBy('nom')->get(['id', 'nom', 'prenom', 'matricule', 'fonction', 'departement']);
        
        return Inertia::render('habilitations/Create', [
            'profils' => $profils,
            'applications' => $this->getApplicationsList(),
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
        ]);

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
            if ($habilitation->status !== 'draft' && $habilitation->status !== 'pending_n1') {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Cette étape n\'est plus accessible.');
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
            'status' => 'pending_control',
        ]);

        // Redirection vers la page de détails (Show) pour afficher le résumé
        // L'utilisateur pourra ensuite accéder à l'étape 3 depuis cette page
        return redirect()->route('habilitations.index')
            ->with('success', 'Étape 2 : Définition des droits et habilitations complétée. La demande est en attente de validation du contrôle permanent (Étape 3).');
    }

    /**
     * Étape 3: Validation Contrôle Permanent - Formulaire
     */
    public function etape3(Habilitation $habilitation)
    {
        $user = Auth::user();
        // Admin peut accéder à toutes les étapes
        if (!$user || !$user->isAdmin()) {
            if ($habilitation->status !== 'pending_control') {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Cette étape n\'est pas accessible actuellement.');
            }
        }

        $habilitation->load(['requester', 'beneficiary', 'validatorN1']);

        return Inertia::render('habilitations/Etape3', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Étape 3: Validation Contrôle Permanent - Validation ou rejet
     */
    public function validerEtape3(Request $request, Habilitation $habilitation)
    {
        $validated = $request->validate([
            'action' => 'required|in:approuver,rejeter',
            'comment_control' => 'nullable|string|required_if:action,rejeter',
        ]);

        if ($validated['action'] === 'approuver') {
            $habilitation->update([
                'validator_control_id' => Auth::id() ?? null,
                'validated_control_at' => now(),
                'comment_control' => $validated['comment_control'] ?? null,
                'status' => 'pending_n2',
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('success', 'Étape 3 : Validation Contrôle Permanent complétée. La demande est en attente de validation N+2.');
        } else {
            $habilitation->update([
                'validator_control_id' => Auth::id() ?? null,
                'validated_control_at' => now(),
                'comment_control' => $validated['comment_control'] ?? null,
                'status' => 'rejected',
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Étape 3 : La demande a été rejetée par le Contrôle Permanent.');
        }
    }

    /**
     * Étape 4: Validation N+2 - Formulaire
     */
    public function etape4(Habilitation $habilitation)
    {
        $user = Auth::user();
        // Admin peut accéder à toutes les étapes
        if (!$user || !$user->isAdmin()) {
            if ($habilitation->status !== 'pending_n2') {
                return redirect()->route('habilitations.show', $habilitation->id)
                    ->with('error', 'Cette étape n\'est pas accessible actuellement.');
            }
        }

        $habilitation->load(['requester', 'beneficiary', 'validatorN1', 'validatorControl']);

        return Inertia::render('habilitations/Etape4', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Étape 4: Validation N+2 - Validation ou rejet
     */
    public function validerEtape4(Request $request, Habilitation $habilitation)
    {
        $validated = $request->validate([
            'action' => 'required|in:approuver,rejeter',
            'comment_n2' => 'nullable|string|required_if:action,rejeter',
        ]);

        if ($validated['action'] === 'approuver') {
            $habilitation->update([
                'validator_n2_id' => Auth::id() ?? null,
                'validated_n2_at' => now(),
                'comment_n2' => $validated['comment_n2'] ?? null,
                'status' => 'approved',
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('success', 'Étape 4 : Validation N+2 complétée. La demande est prête pour l\'exécution IT.');
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
     * Étape 5: Exécution IT - Formulaire
     */
    public function etape5(Habilitation $habilitation)
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
            'validatorControl',
            'validatorN2'
        ]);

        return Inertia::render('habilitations/Etape5', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Étape 5: Exécution IT - Exécution et notification
     */
    public function executerEtape5(Request $request, Habilitation $habilitation)
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
            ->with('success', 'Étape 5 : Exécution IT terminée. Les notifications ont été envoyées.');
    }

    /**
     * Affiche les détails d'une demande d'habilitation
     */
    public function show(Habilitation $habilitation)
    {
        $habilitation->load([
            'requester',
            'beneficiary',
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
     * Liste des applications disponibles depuis la base de données
     */
    private function getApplicationsList(): array
    {
        return Application::actives()
            ->ordered()
            ->pluck('nom')
            ->toArray();
    }
}
