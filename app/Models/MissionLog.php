<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionLog extends Model
{
    /**
     * La table MySQL associée à ce modèle.
     */
    protected $table = 'mission_logs';

    /**
     * Les colonnes de la boîte noire autorisées à recevoir des données.
     */
    protected $fillable = [
        'mission_id',
        'user_id',
        'action',
        'etape_concernee',
        'commentaire',
        'signature_hash',
        'signature_image',
    ];

    /**
     * RELATION 1 : La mission liée
     * Permet de savoir à quel ordre de mission appartient ce log.
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    
 
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
