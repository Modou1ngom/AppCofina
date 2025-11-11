<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Habilitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'demandeur_profile_id',
        'demandeur_direction',
        'demandeur_email',
        'demandeur_telephone',
        'beneficiaire_profile_id',
        'beneficiaire_direction',
        'beneficiaire_email',
        'beneficiaire_telephone',
        'beneficiaire_site',
        'type_demande',
        'applications',
        'autre_application',
        'profil_actuel',
        'profil_demande',
        'date_implementation_souhaitee',
        'type_profil',
        'profil_specifique',
        'periode_validite',
        'date_debut',
        'date_fin',
        'motif_demande',
        'filiale',
        'statut',
        'validateur_n1_id',
        'validation_n1_at',
        'commentaire_n1',
        'validateur_controle_id',
        'validation_controle_at',
        'commentaire_controle',
        'validateur_n2_id',
        'validation_n2_at',
        'commentaire_n2',
        'executeur_it_id',
        'execution_it_at',
        'commentaire_it',
        'notifie_demandeur',
        'notifie_n1',
    ];

    protected $casts = [
        'applications' => 'array',
        'date_implementation_souhaitee' => 'date',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'validation_n1_at' => 'datetime',
        'validation_controle_at' => 'datetime',
        'validation_n2_at' => 'datetime',
        'execution_it_at' => 'datetime',
        'notifie_demandeur' => 'boolean',
        'notifie_n1' => 'boolean',
    ];

    // Relations
    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'demandeur_profile_id');
    }

    public function beneficiaire(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'beneficiaire_profile_id');
    }

    public function validateurN1(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'validateur_n1_id');
    }

    public function validateurControle(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'validateur_controle_id');
    }

    public function validateurN2(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'validateur_n2_id');
    }

    public function executeurIt(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'executeur_it_id');
    }

    // Méthodes utilitaires pour le workflow
    public function peutEtreValideParN1(): bool
    {
        return $this->statut === 'en_attente_n1';
    }

    public function peutEtreValideParControle(): bool
    {
        return $this->statut === 'en_attente_controle';
    }

    public function peutEtreValideParN2(): bool
    {
        return $this->statut === 'en_attente_n2';
    }

    public function peutEtreExecutee(): bool
    {
        return $this->statut === 'approuvee';
    }

    public function passerEtapeSuivante(): void
    {
        $this->statut = match($this->statut) {
            'brouillon' => 'en_attente_n1',
            'en_attente_n1' => 'en_attente_controle',
            'en_attente_controle' => 'en_attente_n2',
            'en_attente_n2' => 'approuvee',
            'approuvee' => 'en_cours_execution',
            'en_cours_execution' => 'terminee',
            default => $this->statut,
        };
        $this->save();
    }

    public function rejeter(string $commentaire = null, string $par = 'n1'): void
    {
        $this->statut = 'rejetee';
        if ($commentaire) {
            // Ajouter le commentaire selon l'étape
            if ($par === 'n1') {
                $this->commentaire_n1 = $commentaire;
            } elseif ($par === 'controle') {
                $this->commentaire_controle = $commentaire;
            } elseif ($par === 'n2') {
                $this->commentaire_n2 = $commentaire;
            }
        }
        $this->save();
    }
}
