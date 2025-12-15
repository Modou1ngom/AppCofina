<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profil extends Model
{
    use HasFactory;

    protected $table = 'profiles';

    protected $fillable = [
        'matricule',
        'prenom',
        'nom',
        'fonction',
        'departement',
        'email',
        'telephone',
        'site',
        'type_contrat',
        'statut',
        'type_office',
        'n_plus_1_id',
        'n_plus_2_id',
        'filiale_id'
    ];

    // Relations
    public function nPlus1()
    {
        return $this->belongsTo(Profil::class, 'n_plus_1_id');
    }

    public function nPlus2()
    {
        return $this->belongsTo(Profil::class, 'n_plus_2_id');
    }

    public function subordonnes()
    {
        return $this->hasMany(Profil::class, 'n_plus_1_id');
    }

    // Alias pour compatibilité ascendante
    public function superieurHierarchique()
    {
        return $this->nPlus1();
    }

    public function habilitationsEnTantQueDemandeur()
    {
        return $this->hasMany(Habilitation::class, 'requester_profile_id');
    }

    public function habilitationsEnTantQueBeneficiaire()
    {
        return $this->hasMany(Habilitation::class, 'beneficiary_profile_id');
    }
    
    // Méthodes alias pour compatibilité ascendante
    public function habilitationsAsRequester()
    {
        return $this->habilitationsEnTantQueDemandeur();
    }
    
    public function habilitationsAsBeneficiary()
    {
        return $this->habilitationsEnTantQueBeneficiaire();
    }

    /**
     * Relation avec les rôles (many-to-many)
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'profile_role', 'profile_id', 'role_id');
    }

    /**
     * Relation avec la filiale
     */
    public function filiale()
    {
        return $this->belongsTo(Filiale::class, 'filiale_id');
    }

    public function getFullNameAttribute()
    {
        return "{$this->prenom} {$this->nom}";
    }

    /**
     * Normalise "informatique" en "IT" pour le département
     */
    public function getDepartementAttribute($value)
    {
        if (!$value) {
            return $value;
        }
        
        // Normaliser "informatique" en "IT" (insensible à la casse)
        $normalized = preg_replace('/informatique/i', 'IT', $value);
        
        return $normalized;
    }

    /**
     * Normalise "informatique" en "IT" pour la fonction
     */
    public function getFonctionAttribute($value)
    {
        if (!$value) {
            return $value;
        }
        
        // Normaliser "informatique" en "IT" (insensible à la casse)
        $normalized = preg_replace('/informatique/i', 'IT', $value);
        
        return $normalized;
    }

    /**
     * Génère un matricule unique automatiquement
     * Format: M suivi d'un numéro incrémenté (ex: M1, M2, M3, etc.)
     * 
     * @return string
     */
    public static function generateMatricule(): string
    {
        $prefix = 'M0';
        
        // Récupérer tous les matricules qui commencent par "M"
        $matricules = self::where('matricule', 'like', "{$prefix}%")
            ->pluck('matricule')
            ->toArray();
        
        $maxNumber = 0;
        
        foreach ($matricules as $matricule) {
            // Extraire le numéro après "M"
            // Gère les formats: M1, M-2025-0001, etc.
            $numberPart = substr($matricule, 1); // Enlève le "M"
            
            // Si le format est M-YYYY-XXXX, extraire le dernier nombre
            if (preg_match('/-(\d+)$/', $numberPart, $matches)) {
                $number = (int) $matches[1];
            } elseif (preg_match('/^(\d+)/', $numberPart, $matches)) {
                // Format simple M1, M2, etc.
                $number = (int) $matches[1];
            } else {
                // Essayer de convertir directement en extrayant tous les chiffres
                $number = (int) preg_replace('/[^0-9]/', '', $numberPart);
            }
            
            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }
        
        $nextNumber = $maxNumber + 1;
        
        return "{$prefix}{$nextNumber}";
    }
}
