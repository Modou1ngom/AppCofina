<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Filiale extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'description',
        'actif'
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    /**
     * Relation avec les profils (basée sur le nom de la filiale)
     */
    public function profils()
    {
        return Profil::where('site', $this->nom);
    }

    /**
     * Relation avec les agences
     */
    public function agences()
    {
        return $this->hasMany(Agence::class, 'filiale_id');
    }

    /**
     * Relation avec les utilisateurs (many-to-many)
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_filiale', 'filiale_id', 'user_id');
    }
}
