<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionParticipant extends Model
{
    protected $table = 'mission_user';

    protected $fillable = [
        'mission_id',
        'user_id',
        'profil_id',
        'role_dans_mission',
        'vehicule',
        'logement',
        'per_diem',
        'prix_carburant',
        'prix_transport',
        'prix_logement',
        'autres_frais',
        'jours',
        'nuits',
        'logistique_sites',
        'besoin_chauffeur',
        'chauffeur_id',
        'chauffeur_profil_id',
    ];

    protected function casts(): array
    {
        return [
            'per_diem' => 'decimal:2',
            'prix_carburant' => 'decimal:2',
            'prix_transport' => 'decimal:2',
            'prix_logement' => 'decimal:2',
            'autres_frais' => 'decimal:2',
            'jours' => 'integer',
            'nuits' => 'integer',
            'logistique_sites' => 'array',
            'besoin_chauffeur' => 'boolean',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profil(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'profil_id');
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chauffeur_id');
    }

    public function chauffeurProfil(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'chauffeur_profil_id');
    }

    public function estMissionnaire(): bool
    {
        return ($this->role_dans_mission ?? 'missionnaire') === 'missionnaire';
    }

    public function nomAffichage(): string
    {
        $this->loadMissing(['profil', 'user']);

        if ($this->profil) {
            $nom = trim(($this->profil->prenom ?? '') . ' ' . ($this->profil->nom ?? ''));
            if ($nom !== '') {
                return $nom;
            }
        }

        return $this->user?->name ?? '—';
    }
}
