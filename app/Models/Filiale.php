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
}
