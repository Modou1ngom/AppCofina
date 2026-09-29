<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnqueteSatisfactionSuivi extends Model
{
    protected $table = 'enquete_satisfaction_suivis';

    public const TYPES = [
        'remarque' => 'Remarques / difficultés rencontrées',
        'suggestion' => 'Suggestions d’amélioration',
        'besoin' => 'Besoins ou attentes supplémentaires',
    ];

    public const DECISIONS = [
        'prendre_en_charge' => 'Prendre en charge',
        'rejeter' => 'Rejeter',
        'clarifier' => 'Clarifier',
    ];

    public const STATUTS = [
        'a_analyser' => 'À analyser',
        'a_clarifier' => 'À clarifier',
        'en_cours' => 'En cours',
        'cloture' => 'Clôturé',
        'rejete' => 'Rejeté',
    ];

    protected $fillable = [
        'reponse_id',
        'type',
        'decision',
        'statut',
        'element_reponse',
        'action',
        'justification',
        'date_cloture',
    ];

    protected function casts(): array
    {
        return [
            'date_cloture' => 'date',
        ];
    }

    public function reponse(): BelongsTo
    {
        return $this->belongsTo(EnqueteSatisfactionReponse::class, 'reponse_id');
    }
}
