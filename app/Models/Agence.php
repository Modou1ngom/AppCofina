<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agence extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'code_agent',
        'description',
        'actif',
        'chef_agence_id'
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    /**
     * Relation avec les profils (basée sur le nom de l'agence)
     */
    public function profils()
    {
        return Profil::where('site', $this->nom);
    }

    /**
     * Relation avec le chef d'agence
     */
    public function chefAgence()
    {
        return $this->belongsTo(Profil::class, 'chef_agence_id');
    }
}
