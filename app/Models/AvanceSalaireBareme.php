<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvanceSalaireBareme extends Model
{
    protected $table = 'avance_salaire_baremes';

    protected $fillable = [
        'key',
        'label',
        'compte_charge',
        'code_operation',
        'duree_max_mois',
        'plafond_non_cadre',
        'plafond_cadre',
        'plafond_emc',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duree_max_mois' => 'integer',
            'plafond_non_cadre' => 'decimal:2',
            'plafond_cadre' => 'decimal:2',
            'plafond_emc' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
