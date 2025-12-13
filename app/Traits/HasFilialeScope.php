<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasFilialeScope
{
    /**
     * Boot le trait et ajoute le scope global pour filtrer par filiale
     */
    protected static function bootHasFilialeScope()
    {
        static::addGlobalScope('filiale', function (Builder $builder) {
            $filialeId = session('current_filiale_id');
            
            // Si une filiale est définie dans la session, filtrer par celle-ci
            if ($filialeId) {
                $builder->where('filiale_id', $filialeId);
            }
        });
    }

    /**
     * Scope pour récupérer toutes les données sans filtre de filiale
     */
    public function scopeWithoutFilialeScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('filiale');
    }

    /**
     * Scope pour récupérer les données d'une filiale spécifique
     */
    public function scopeForFiliale(Builder $query, $filialeId): Builder
    {
        return $query->withoutGlobalScope('filiale')->where('filiale_id', $filialeId);
    }
}

