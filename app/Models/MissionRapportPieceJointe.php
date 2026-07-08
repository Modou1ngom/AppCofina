<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionRapportPieceJointe extends Model
{
    protected $table = 'mission_rapport_pieces_jointes';

    protected $fillable = [
        'mission_id',
        'user_id',
        'nom_fichier',
        'chemin',
        'mime_type',
        'taille',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
