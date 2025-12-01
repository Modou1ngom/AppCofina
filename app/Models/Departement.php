<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Departement extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'description',
        'actif',
        'responsable_departement_id'
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    /**
     * Relation avec les profils (basée sur le nom du département)
     */
    public function profils()
    {
        return Profil::where('departement', $this->nom);
    }

    /**
     * Relation avec le responsable du département
     */
    public function responsable()
    {
        return $this->belongsTo(Profil::class, 'responsable_departement_id');
    }
}
