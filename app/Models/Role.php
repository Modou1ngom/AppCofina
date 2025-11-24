<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'slug',
        'description',
        'actif'
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    /**
     * Relation avec les profils (many-to-many)
     */
    public function profils()
    {
        return $this->belongsToMany(Profil::class, 'profile_role', 'role_id', 'profile_id');
    }
}
