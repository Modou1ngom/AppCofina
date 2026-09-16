<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilMouvement extends Model
{
    public const TYPE_ARRIVEE = 'arrivee';

    public const TYPE_DEPART = 'depart';

    public const TYPE_CHANGEMENT_POSTE = 'changement_poste';

    protected $fillable = [
        'profil_id',
        'type',
        'date_effet',
        'fonction_avant',
        'fonction_apres',
        'departement_avant',
        'departement_apres',
        'site_avant',
        'site_apres',
        'n_plus_1_id_avant',
        'n_plus_1_id_apres',
        'motif',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_effet' => 'date',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_ARRIVEE => 'Arrivée',
            self::TYPE_DEPART => 'Départ',
            self::TYPE_CHANGEMENT_POSTE => 'Changement de poste',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function profil(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'profil_id');
    }

    public function nPlus1Avant(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'n_plus_1_id_avant');
    }

    public function nPlus1Apres(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'n_plus_1_id_apres');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
