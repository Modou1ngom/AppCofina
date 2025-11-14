<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Habilitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_profile_id',
        'requester_direction',
        'requester_email',
        'requester_telephone',
        'beneficiary_profile_id',
        'beneficiary_direction',
        'beneficiary_email',
        'beneficiary_telephone',
        'beneficiary_site',
        'request_type',
        'applications',
        'other_application',
        'current_profile',
        'requested_profile',
        'desired_implementation_date',
        'profile_type',
        'specific_profile',
        'validity_period',
        'start_date',
        'end_date',
        'request_reason',
        'subsidiary',
        'status',
        'validator_n1_id',
        'validated_n1_at',
        'comment_n1',
        'validator_control_id',
        'validated_control_at',
        'comment_control',
        'validator_n2_id',
        'validated_n2_at',
        'comment_n2',
        'executor_it_id',
        'executed_it_at',
        'comment_it',
        'notify_requester',
        'notify_n1',
    ];

    protected $casts = [
        'applications' => 'array',
        'desired_implementation_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'validated_n1_at' => 'datetime',
        'validated_control_at' => 'datetime',
        'validated_n2_at' => 'datetime',
        'executed_it_at' => 'datetime',
        'notify_requester' => 'boolean',
        'notify_n1' => 'boolean',
    ];

    // Relations
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'requester_profile_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'beneficiary_profile_id');
    }

    public function validatorN1(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'validator_n1_id');
    }

    public function validatorControl(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'validator_control_id');
    }

    public function validatorN2(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'validator_n2_id');
    }

    public function executorIt(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'executor_it_id');
    }

    // Méthodes alias pour compatibilité (si nécessaire)
    public function demandeur(): BelongsTo
    {
        return $this->requester();
    }

    public function beneficiaire(): BelongsTo
    {
        return $this->beneficiary();
    }

    // Méthodes utilitaires pour le workflow
    public function canBeValidatedByN1(): bool
    {
        return $this->status === 'pending_n1';
    }

    public function canBeValidatedByControl(): bool
    {
        return $this->status === 'pending_control';
    }

    public function canBeValidatedByN2(): bool
    {
        return $this->status === 'pending_n2';
    }

    public function canBeExecuted(): bool
    {
        return $this->status === 'approved';
    }

    public function moveToNextStep(): void
    {
        $this->status = match($this->status) {
            'draft' => 'pending_n1',
            'pending_n1' => 'pending_control',
            'pending_control' => 'pending_n2',
            'pending_n2' => 'approved',
            'approved' => 'in_progress',
            'in_progress' => 'completed',
            default => $this->status,
        };
        $this->save();
    }

    public function reject(?string $comment = null, string $by = 'n1'): void
    {
        $this->status = 'rejected';
        if ($comment) {
            // Ajouter le commentaire selon l'étape
            if ($by === 'n1') {
                $this->comment_n1 = $comment;
            } elseif ($by === 'control') {
                $this->comment_control = $comment;
            } elseif ($by === 'n2') {
                $this->comment_n2 = $comment;
            }
        }
        $this->save();
    }
}
