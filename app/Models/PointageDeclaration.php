<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointageDeclaration extends Model
{
    public const STATUT_PENDING_MANAGER = 'pending_manager';

    public const STATUT_REJECTED_BY_MANAGER = 'rejected_by_manager';

    public const STATUT_PENDING_RH = 'pending_rh';

    public const STATUT_REJECTED_BY_RH = 'rejected_by_rh';

    public const STATUT_APPROVED = 'approved';

    protected $fillable = [
        'user_id',
        'date_concernee',
        'motif',
        'statut',
        'manager_user_id',
        'decided_at_manager',
        'commentaire_manager',
        'rh_user_id',
        'decided_at_rh',
        'commentaire_rh',
    ];

    protected function casts(): array
    {
        return [
            'date_concernee' => 'date',
            'decided_at_manager' => 'datetime',
            'decided_at_rh' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function rh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rh_user_id');
    }
}
