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
        'superieur_hierarchique_id'
    ];

    // Relations
    public function superieurHierarchique()
    {
        return $this->belongsTo(Profil::class, 'superieur_hierarchique_id');
    }

    public function subordonnes()
    {
        return $this->hasMany(Profil::class, 'superieur_hierarchique_id');
    }

    public function habilitationsEnTantQueDemandeur()
    {
        return $this->hasMany(Habilitation::class, 'demandeur_profile_id');
    }

    public function habilitationsEnTantQueBeneficiaire()
    {
        return $this->hasMany(Habilitation::class, 'beneficiaire_profile_id');
    }

    public function getFullNameAttribute()
    {
        return "{$this->prenom} {$this->nom}";
    }
}
