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
        'n_plus_1_id',
        'n_plus_2_id'
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

    public function getFullNameAttribute()
    {
        return "{$this->prenom} {$this->nom}";
    }

    /**
     * Génère un matricule unique automatiquement
     * Format: MAT-YYYY-XXXX (ex: MAT-2025-0001)
     * 
     * @return string
     */
    public static function generateMatricule(): string
    {
        $year = date('Y');
        $prefix = 'MAT';
        
        // Trouver le dernier matricule de l'année en cours
        $lastMatricule = self::where('matricule', 'like', "{$prefix}-{$year}-%")
            ->orderBy('matricule', 'desc')
            ->value('matricule');
        
        if ($lastMatricule) {
            // Extraire le numéro séquentiel
            $parts = explode('-', $lastMatricule);
            $lastNumber = isset($parts[2]) ? (int)$parts[2] : 0;
            $nextNumber = $lastNumber + 1;
        } else {
            // Premier matricule de l'année
            $nextNumber = 1;
        }
        
        // Formater avec 4 chiffres (0001, 0002, etc.)
        $formattedNumber = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$year}-{$formattedNumber}";
    }
}
