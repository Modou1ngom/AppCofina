<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvanceSalaireDemande extends Model
{
    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_SOUMISE = 'soumise';

    public const STATUT_EN_VALIDATION_FINANCE = 'en_validation_finance';

    public const STATUT_EN_ATTENTE = 'en_attente';

    /** @deprecated Conservé pour compatibilité ; la validation se termine par {@see STATUT_EN_ATTENTE_PRISE_EN_CHARGE}. */
    public const STATUT_APPROUVEE = 'approuvee';

    /** Validation terminée : en attente du traitement opérationnel RH. */
    public const STATUT_EN_ATTENTE_PRISE_EN_CHARGE = 'en_attente_prise_en_charge';

    /** RH a pris en charge : traitement en cours (paie / paiement). */
    public const STATUT_EN_COURS_TRAITEMENT = 'en_cours_traitement';

    /** Traitement opérationnel RH terminé. */
    public const STATUT_TERMINEE = 'terminee';

    public const STATUT_REJETEE = 'rejetee';

    protected $table = 'avance_salaire_demandes';

    protected $fillable = [
        'user_id',
        'profile_id',
        'matricule',
        'nom',
        'prenom',
        'type_avance',
        'mode_paiement',
        'dates_tranches',
        'categorie_staff',
        'montant',
        'duree_mois',
        'compte_staff',
        'nombre_avance_en_cours',
        'date_premiere_echeance',
        'salaire_net',
        'salaire_domicilie',
        'taux_interet_annuel_pct',
        'plafond_pct_applique',
        'montant_max_autorise',
        'eligible',
        'eligibilite_messages',
        'mensualite',
        'date_fin_prevue',
        'tableau_amortissement',
        'statut',
        'statut_avant_attente',
        'rh_decided_at',
        'rh_decided_by',
        'rh_commentaire',
        'rh_niveau_finance',
        'cfo_validated_at',
        'cfo_validated_by',
        'cfo_commentaire',
        'md_validated_at',
        'md_validated_by',
        'md_commentaire',
        'finance_decided_at',
        'finance_decided_by',
        'finance_commentaire',
        'signature_employe',
        'signature_employe_by',
        'signature_employe_at',
        'signature_rh',
        'signature_rh_by',
        'signature_rh_at',
        'signature_finance',
        'signature_finance_by',
        'signature_finance_at',
        'rh_prise_en_charge_at',
        'rh_prise_en_charge_by',
        'rh_traitement_termine_at',
        'rh_traitement_termine_by',
        'filiale_id',
    ];

    protected function casts(): array
    {
        return [
            'date_premiere_echeance' => 'date',
            'date_fin_prevue' => 'date',
            'montant' => 'decimal:2',
            'salaire_net' => 'decimal:2',
            'salaire_domicilie' => 'boolean',
            'taux_interet_annuel_pct' => 'decimal:2',
            'plafond_pct_applique' => 'decimal:2',
            'montant_max_autorise' => 'decimal:2',
            'eligible' => 'boolean',
            'eligibilite_messages' => 'array',
            'dates_tranches' => 'array',
            'mensualite' => 'decimal:2',
            'tableau_amortissement' => 'array',
            'rh_decided_at' => 'datetime',
            'cfo_validated_at' => 'datetime',
            'md_validated_at' => 'datetime',
            'finance_decided_at' => 'datetime',
            'signature_employe_at' => 'datetime',
            'signature_rh_at' => 'datetime',
            'signature_finance_at' => 'datetime',
            'rh_prise_en_charge_at' => 'datetime',
            'rh_traitement_termine_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'profile_id');
    }

    public function rhDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rh_decided_by');
    }

    public function financeDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_decided_by');
    }

    public function rhPriseEnChargeBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rh_prise_en_charge_by');
    }

    public function rhTraitementTermineBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rh_traitement_termine_by');
    }

    public function cfoValidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cfo_validated_by');
    }

    public function mdValidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'md_validated_by');
    }

    public function filiale(): BelongsTo
    {
        return $this->belongsTo(Filiale::class, 'filiale_id');
    }

    public function signatureEmployeBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signature_employe_by');
    }

    public function signatureRhBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signature_rh_by');
    }

    public function signatureFinanceBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signature_finance_by');
    }

    public static function statutsWorkflow(): array
    {
        return [
            self::STATUT_BROUILLON,
            self::STATUT_SOUMISE,
            self::STATUT_EN_VALIDATION_FINANCE,
            self::STATUT_EN_ATTENTE,
            self::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
            self::STATUT_EN_COURS_TRAITEMENT,
            self::STATUT_TERMINEE,
            self::STATUT_REJETEE,
        ];
    }

    /**
     * Libellé métier lisible pour l’utilisateur (workflow : soumission → RH / CFO-MD → intégration).
     */
    public function libelleStatutWorkflow(): string
    {
        $s = $this->statut;
        $avant = $this->statut_avant_attente;

        if ($s === self::STATUT_BROUILLON) {
            return 'Brouillon';
        }
        if ($s === self::STATUT_SOUMISE) {
            return 'Demande soumise — en attente des RH';
        }
        if ($s === self::STATUT_EN_ATTENTE && $avant === self::STATUT_SOUMISE) {
            return 'Mise en attente par les RH';
        }
        if ($s === self::STATUT_EN_VALIDATION_FINANCE) {
            return $this->libelleCircuitValidationFinance();
        }
        if ($s === self::STATUT_EN_ATTENTE && $avant === self::STATUT_EN_VALIDATION_FINANCE) {
            return 'Mise en attente (CFO / MD)';
        }
        if ($s === self::STATUT_EN_ATTENTE_PRISE_EN_CHARGE) {
            return 'En attente d’intégration';
        }
        if ($s === self::STATUT_EN_COURS_TRAITEMENT) {
            return 'En cours d’intégration';
        }
        if ($s === self::STATUT_TERMINEE) {
            return 'Terminée';
        }
        if ($s === self::STATUT_REJETEE) {
            return 'Demande rejetée';
        }
        if ($s === self::STATUT_APPROUVEE) {
            return 'Approuvée';
        }
        if ($s === self::STATUT_EN_ATTENTE) {
            return 'En attente';
        }

        return $s;
    }

    private function libelleCircuitValidationFinance(): string
    {
        $niveau = $this->rh_niveau_finance;
        if ($niveau === 'cfo') {
            return 'Transmise au CFO — validation en cours';
        }
        if ($niveau === 'md') {
            if ($this->cfo_validated_at !== null) {
                return 'Transmise au MD — validation en cours';
            }

            return 'Transmise au CFO puis au MD — validation CFO en cours';
        }

        return 'En validation CFO / MD';
    }

    /** Statuts après validation favorable (circuit RH / finance terminé). */
    public static function statutsApresValidationFavorable(): array
    {
        return [
            self::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
            self::STATUT_EN_COURS_TRAITEMENT,
            self::STATUT_TERMINEE,
            self::STATUT_APPROUVEE,
        ];
    }

    public function isEditableByOwner(): bool
    {
        return $this->statut === self::STATUT_BROUILLON;
    }

    public static function scopeActivesPourProfile($query, int $profileId, ?int $excludeDemandeId = null)
    {
        return $query->where('profile_id', $profileId)
            ->when($excludeDemandeId, fn ($q) => $q->where('id', '!=', $excludeDemandeId))
            ->whereIn('statut', [
                self::STATUT_SOUMISE,
                self::STATUT_EN_VALIDATION_FINANCE,
                self::STATUT_EN_ATTENTE,
                self::STATUT_EN_ATTENTE_PRISE_EN_CHARGE,
                self::STATUT_EN_COURS_TRAITEMENT,
                self::STATUT_TERMINEE,
                self::STATUT_APPROUVEE,
            ]);
    }
}
