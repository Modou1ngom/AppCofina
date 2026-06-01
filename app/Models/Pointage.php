<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pointage extends Model
{
    public const SENS_ENTREE = 'entree';

    public const SENS_SORTIE = 'sortie';

    protected $fillable = [
        'user_id',
        'pointage_site_id',
        'sens',
        'enregistre_at',
        'source',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'enregistre_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(PointageSite::class, 'pointage_site_id');
    }
}
