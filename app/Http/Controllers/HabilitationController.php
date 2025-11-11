<?php

namespace App\Http\Controllers;

use App\Models\Habilitation;
use App\Models\Profil;
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
        $habilitations = Habilitation::with(['demandeur', 'beneficiaire', 'validateurN1', 'validateurControle', 'validateurN2', 'executeurIt'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('habilitations/Index', [
            'habilitations' => $habilitations,
        ]);
    }

    /**
     * Étape 1: Formulaire de création de demande (Profil RH)
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
     * Étape 1: Sauvegarde de la demande (Profil RH)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Demandeur
            'demandeur_profile_id' => 'required|exists:profiles,id',
            'demandeur_direction' => 'nullable|string|max:255',
            'demandeur_email' => 'nullable|email|max:255',
            'demandeur_telephone' => 'nullable|string|max:20',
            
            // Bénéficiaire
            'beneficiaire_profile_id' => 'required|exists:profiles,id',
            'beneficiaire_direction' => 'nullable|string|max:255',
            'beneficiaire_email' => 'nullable|email|max:255',
            'beneficiaire_telephone' => 'nullable|string|max:20',
            'beneficiaire_site' => 'nullable|string|max:255',
            
            // Détails de la demande
            'type_demande' => 'required|in:Creation,Modification,Desactivation,Suppression',
            'applications' => 'required|array|min:1',
            'applications.*' => 'string',
            'autre_application' => 'nullable|string|max:255',
            'profil_actuel' => 'nullable|string',
            'profil_demande' => 'nullable|string',
            'date_implementation_souhaitee' => 'nullable|date',
            'type_profil' => 'nullable|in:Consultation simple,Profil Specifique',
            'profil_specifique' => 'nullable|string',
            'periode_validite' => 'nullable|in:Permanent,Temporaire',
            'date_debut' => 'nullable|date|required_if:periode_validite,Temporaire',
            'date_fin' => 'nullable|date|after:date_debut|required_if:periode_validite,Temporaire',
            'motif_demande' => 'nullable|string',
            'filiale' => 'nullable|string|max:255',
        ]);

        $habilitation = Habilitation::create([
            ...$validated,
            'statut' => 'brouillon',
        ]);

        return redirect()->route('habilitations.etape2', $habilitation->id)
            ->with('success', 'Étape 1 complétée. Veuillez définir les droits et habilitations.');
    }

    /**
     * Étape 2: Définition des droits et habilitations par N+1
     */
    public function etape2(Habilitation $habilitation)
    {
        if ($habilitation->statut !== 'brouillon' && $habilitation->statut !== 'en_attente_n1') {
            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Cette étape n\'est plus accessible.');
        }

        $habilitation->load(['demandeur', 'beneficiaire']);

        return Inertia::render('habilitations/Etape2', [
            'habilitation' => $habilitation,
            'applications' => $this->getApplicationsList(),
        ]);
    }

    /**
     * Étape 2: Sauvegarde des droits et habilitations
     */
    public function updateEtape2(Request $request, Habilitation $habilitation)
    {
        $validated = $request->validate([
            'profil_demande' => 'required|string',
            'type_profil' => 'required|in:Consultation simple,Profil Specifique',
            'profil_specifique' => 'nullable|string|required_if:type_profil,Profil Specifique',
            'applications' => 'required|array|min:1',
            'applications.*' => 'string',
            'autre_application' => 'nullable|string|max:255',
            'commentaire_n1' => 'nullable|string',
        ]);

        $habilitation->update([
            'profil_demande' => $validated['profil_demande'],
            'type_profil' => $validated['type_profil'],
            'profil_specifique' => $validated['profil_specifique'] ?? null,
            'applications' => $validated['applications'],
            'autre_application' => $validated['autre_application'] ?? null,
            'commentaire_n1' => $validated['commentaire_n1'] ?? null,
            'statut' => 'en_attente_controle',
        ]);

        return redirect()->route('habilitations.show', $habilitation->id)
            ->with('success', 'Étape 2 complétée. La demande est en attente de validation du contrôle permanent.');
    }

    /**
     * Étape 3: Validation Contrôle Permanent
     */
    public function etape3(Habilitation $habilitation)
    {
        if ($habilitation->statut !== 'en_attente_controle') {
            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Cette étape n\'est pas accessible actuellement.');
        }

        $habilitation->load(['demandeur', 'beneficiaire', 'validateurN1']);

        return Inertia::render('habilitations/Etape3', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Étape 3: Validation ou rejet par le Contrôle Permanent
     */
    public function validerEtape3(Request $request, Habilitation $habilitation)
    {
        $validated = $request->validate([
            'action' => 'required|in:approuver,rejeter',
            'commentaire_controle' => 'nullable|string|required_if:action,rejeter',
        ]);

        if ($validated['action'] === 'approuver') {
            $habilitation->update([
                'validateur_controle_id' => Auth::id() ?? null,
                'validation_controle_at' => now(),
                'commentaire_controle' => $validated['commentaire_controle'] ?? null,
                'statut' => 'en_attente_n2',
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('success', 'Validation effectuée. La demande est en attente de validation N+2.');
        } else {
            $habilitation->rejeter($validated['commentaire_controle'], 'controle');
            $habilitation->update([
                'validateur_controle_id' => Auth::id() ?? null,
                'validation_controle_at' => now(),
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'La demande a été rejetée par le contrôle permanent.');
        }
    }

    /**
     * Étape 4: Validation N+2
     */
    public function etape4(Habilitation $habilitation)
    {
        if ($habilitation->statut !== 'en_attente_n2') {
            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Cette étape n\'est pas accessible actuellement.');
        }

        $habilitation->load(['demandeur', 'beneficiaire', 'validateurN1', 'validateurControle']);

        return Inertia::render('habilitations/Etape4', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Étape 4: Validation ou rejet par N+2
     */
    public function validerEtape4(Request $request, Habilitation $habilitation)
    {
        $validated = $request->validate([
            'action' => 'required|in:approuver,rejeter',
            'commentaire_n2' => 'nullable|string|required_if:action,rejeter',
        ]);

        if ($validated['action'] === 'approuver') {
            $habilitation->update([
                'validateur_n2_id' => Auth::id() ?? null,
                'validation_n2_at' => now(),
                'commentaire_n2' => $validated['commentaire_n2'] ?? null,
                'statut' => 'approuvee',
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('success', 'Validation finale effectuée. La demande est prête pour l\'exécution IT.');
        } else {
            $habilitation->rejeter($validated['commentaire_n2'], 'n2');
            $habilitation->update([
                'validateur_n2_id' => Auth::id() ?? null,
                'validation_n2_at' => now(),
            ]);

            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'La demande a été rejetée par N+2.');
        }
    }

    /**
     * Étape 5: Exécution IT
     */
    public function etape5(Habilitation $habilitation)
    {
        if ($habilitation->statut !== 'approuvee' && $habilitation->statut !== 'en_cours_execution') {
            return redirect()->route('habilitations.show', $habilitation->id)
                ->with('error', 'Cette étape n\'est pas accessible actuellement.');
        }

        $habilitation->load([
            'demandeur',
            'beneficiaire',
            'validateurN1',
            'validateurControle',
            'validateurN2'
        ]);

        return Inertia::render('habilitations/Etape5', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Étape 5: Exécution IT et notification
     */
    public function executerEtape5(Request $request, Habilitation $habilitation)
    {
        $validated = $request->validate([
            'commentaire_it' => 'nullable|string',
            'notifie_demandeur' => 'boolean',
            'notifie_n1' => 'boolean',
        ]);

        $habilitation->update([
            'executeur_it_id' => Auth::id() ?? null,
            'execution_it_at' => now(),
            'commentaire_it' => $validated['commentaire_it'] ?? null,
            'notifie_demandeur' => $validated['notifie_demandeur'] ?? false,
            'notifie_n1' => $validated['notifie_n1'] ?? false,
            'statut' => 'terminee',
        ]);

        // TODO: Implémenter l'envoi de notifications par email

        return redirect()->route('habilitations.show', $habilitation->id)
            ->with('success', 'Exécution IT terminée. Les notifications ont été envoyées.');
    }

    /**
     * Affiche les détails d'une demande d'habilitation
     */
    public function show(Habilitation $habilitation)
    {
        $habilitation->load([
            'demandeur',
            'beneficiaire',
            'validateurN1',
            'validateurControle',
            'validateurN2',
            'executeurIt'
        ]);

        return Inertia::render('habilitations/Show', [
            'habilitation' => $habilitation,
        ]);
    }

    /**
     * Liste des applications disponibles
     */
    private function getApplicationsList(): array
    {
        return [
            'Compte Windows',
            'Outlook',
            'SharePoint',
            'Intranet',
            'Sage Compta',
            'VPN',
            'Jasper',
            'SAGE Paie',
            'GEFA',
            'Work. Aviso',
            'NAFA',
            'Tracking crédit',
            'BD NAFA',
            'PERFECT',
            'Autres',
        ];
    }
}
