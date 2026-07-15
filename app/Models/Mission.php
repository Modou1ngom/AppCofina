<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mission extends Model
{
    public const STEP_BROUILLON = 'BROUILLON';

    public const STEP_ATTENTE_N1 = 'ATTENTE_N1';

    public const STEP_ATTENTE_DGA = 'ATTENTE_DGA';

    public const STEP_ATTENTE_MD = 'ATTENTE_MD';

    public const STEP_ATTENTE_FACILITIES = 'ATTENTE_FACILITIES';

    public const STEP_ATTENTE_RH = 'ATTENTE_RH';

    public const STEP_ATTENTE_SIGNATURE_RRH = 'ATTENTE_SIGNATURE_RRH';

    public const STEP_VALIDEE = 'VALIDEE';

    public const STEP_ATTENTE_FINANCE = 'ATTENTE_FINANCE';

    public const STEP_ATTENTE_RAPPORT = 'ATTENTE_RAPPORT';

    public const STEP_ATTENTE_VALIDATION_RAPPORT = 'ATTENTE_VALIDATION_RAPPORT';

    public const STEP_CLOTUREE = 'CLOTUREE';

    public function libelleNumero(): string
    {
        return $this->numero_mission !== null ? (string) $this->numero_mission : '—';
    }

    protected $table = 'missions';

    protected $fillable = [
        'numero_mission',
        'demandeur_id',
        'beneficiaire_id',
        'n2_beneficiaire_id',
        'objet',
        'description',
        'perimetre',
        'sites_mission',
        'descriptions_sites',
        'sites_prolongation',
        'descriptions_sites_prolongation',
        'enjeux',
        'risques',
        'priorite',
        'date_debut',
        'date_fin',
        'budget',
        'besoin_vehicule',
        'besoin_chauffeur',
        'besoin_hebergement',
        'besoin_transport',
        'commentaire_rh',
        'vehicule_attribue',
        'chauffeur_id',
        'logement_attribue',
        'prix_carburant_estime',
        'prix_transport_estime',
        'prix_logement_estime',
        'autres_frais_logistique',
        'total_logistique',
        'finance_logistique_validee_at',
        'finance_logistique_validee_par',
        'facilities_retour_finance',
        'commentaire_facilities',
        'rapport_contenu',
        'rapport_reponses',
        'rapport_signature_image',
        'rapport_signataire_nom',
        'rapport_signataire_id',
        'rapport_soumis_at',
        'rapport_pdf_path',
        'rapport_valide_at',
        'duree_modifiee_at',
        'prolongation_donnees',
        'etape_reprise_apres_prolongation',
        'last_reminder_at',
        'md_signe_at',
        'dga_contournee',
        'pdf_path',
        'ordre_prolongation_pdf_path',
        'ordre_prolongation_signe_at',
        'current_step',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'besoin_vehicule' => 'boolean',
            'besoin_chauffeur' => 'boolean',
            'besoin_hebergement' => 'boolean',
            'besoin_transport' => 'boolean',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'budget' => 'decimal:2',
            'prix_carburant_estime' => 'decimal:2',
            'prix_transport_estime' => 'decimal:2',
            'prix_logement_estime' => 'decimal:2',
            'autres_frais_logistique' => 'decimal:2',
            'total_logistique' => 'decimal:2',
            'finance_logistique_validee_at' => 'datetime',
            'rapport_soumis_at' => 'datetime',
            'rapport_reponses' => 'array',
            'rapport_valide_at' => 'datetime',
            'duree_modifiee_at' => 'datetime',
            'prolongation_donnees' => 'array',
            'ordre_prolongation_signe_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'md_signe_at' => 'datetime',
            'dga_contournee' => 'boolean',
            'facilities_retour_finance' => 'boolean',
            'sites_mission' => 'array',
            'descriptions_sites' => 'array',
            'sites_prolongation' => 'array',
            'descriptions_sites_prolongation' => 'array',
        ];
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    public function beneficiaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiaire_id');
    }

    /** Validateur N+1 du demandeur */
    public function n1Validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'n2_beneficiaire_id');
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chauffeur_id');
    }

    public function rapportSignataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rapport_signataire_id');
    }

    public function financeLogistiqueValidateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_logistique_validee_par');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mission_user', 'mission_id', 'user_id')
            ->withPivot([
                'role_dans_mission',
                'vehicule',
                'logement',
                'per_diem',
                'prix_carburant',
                'prix_transport',
                'prix_logement',
                'autres_frais',
                'besoin_chauffeur',
                'chauffeur_id',
            ])
            ->withTimestamps();
    }

    public function missionnaires(): BelongsToMany
    {
        return $this->participants()->wherePivot('role_dans_mission', 'missionnaire');
    }

    public function missionParticipants(): HasMany
    {
        return $this->hasMany(MissionParticipant::class, 'mission_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MissionLog::class, 'mission_id');
    }

    public function rapportPiecesJointes(): HasMany
    {
        return $this->hasMany(MissionRapportPieceJointe::class, 'mission_id');
    }

    public function totalLogistique(): float
    {
        if ($this->total_logistique !== null) {
            return (float) $this->total_logistique;
        }

        return (float) $this->prix_carburant_estime
            + (float) $this->prix_transport_estime
            + (float) $this->prix_logement_estime
            + (float) ($this->autres_frais_logistique ?? 0);
    }

    public static function totalLigneParticipant(?object $pivot): float
    {
        if ($pivot === null) {
            return 0.0;
        }

        return (float) ($pivot->per_diem ?? 0)
            + (float) ($pivot->prix_carburant ?? 0)
            + (float) ($pivot->prix_transport ?? 0)
            + (float) ($pivot->prix_logement ?? 0)
            + (float) ($pivot->autres_frais ?? 0);
    }
}
