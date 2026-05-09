<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SigStaffEncoursConformiteEvent extends Model
{
    public const TYPE_DEPASSEMENT = 'depassement';

    public const TYPE_RETOUR_CONFORME = 'retour_conforme';

    public const TYPE_COMMENTAIRE = 'commentaire';

    protected $table = 'sig_staff_encours_conformite_events';

    protected $fillable = [
        'sig_staff_id',
        'user_id',
        'type',
        'fonds_propres',
        'encours_consolide',
        'taux_pct',
        'seuil_pct',
        'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'fonds_propres' => 'decimal:2',
            'encours_consolide' => 'decimal:2',
            'taux_pct' => 'decimal:2',
            'seuil_pct' => 'decimal:2',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(SigStaff::class, 'sig_staff_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_DEPASSEMENT => 'Détection dépassement',
            self::TYPE_RETOUR_CONFORME => 'Retour sous le seuil',
            self::TYPE_COMMENTAIRE => 'Commentaire / justification',
            default => $type,
        };
    }
}
