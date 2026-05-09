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
        'latitude',
        'longitude',
        'actif',
        'chef_agence_id',
        'filiale_id',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
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

    /**
     * Relation avec la filiale
     */
    public function filiale()
    {
        return $this->belongsTo(Filiale::class, 'filiale_id');
    }

    /**
     * Relation avec les utilisateurs (many-to-many).
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'agence_user', 'agence_id', 'user_id')
            ->withPivot('is_default')
            ->withTimestamps();
    }
}
