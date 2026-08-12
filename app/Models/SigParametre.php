<?php

namespace App\Models;

use App\Helpers\FilialeHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SigParametre extends Model
{
    protected $table = 'sig_parametres';

    protected $fillable = [
        'filiale_id',
        'fonds_propres',
        'seuil_taux_pct',
        'alerte_taux_pct',
    ];

    protected function casts(): array
    {
        return [
            'fonds_propres' => 'decimal:2',
            'seuil_taux_pct' => 'decimal:2',
            'alerte_taux_pct' => 'decimal:2',
        ];
    }

    public function filiale(): BelongsTo
    {
        return $this->belongsTo(Filiale::class, 'filiale_id');
    }

    /**
     * Paramètres de conformité pour l'environnement / filiale courant.
     */
    public static function current(?int $filialeId = null): self
    {
        $filialeId = $filialeId ?? FilialeHelper::getCurrentFilialeId();
        $cacheKey = 'sig_parametres_current_'.($filialeId ?? 'none');

        return Cache::remember($cacheKey, 60, function () use ($filialeId) {
            $query = static::query()->orderBy('id');
            if ($filialeId) {
                $row = (clone $query)->where('filiale_id', $filialeId)->first();
                if ($row !== null) {
                    return $row;
                }

                return static::query()->create([
                    'filiale_id' => $filialeId,
                    'fonds_propres' => null,
                    'seuil_taux_pct' => (float) config('sig.encours_taux_seuil_pct', 10),
                    'alerte_taux_pct' => (float) config('sig.encours_taux_alerte_pct', 8),
                ]);
            }

            // Fallback legacy (pas de filiale en session)
            $row = $query->whereNull('filiale_id')->first() ?? $query->first();
            if ($row !== null) {
                return $row;
            }

            return static::query()->create([
                'filiale_id' => null,
                'fonds_propres' => null,
                'seuil_taux_pct' => (float) config('sig.encours_taux_seuil_pct', 10),
                'alerte_taux_pct' => (float) config('sig.encours_taux_alerte_pct', 8),
            ]);
        });
    }

    public static function forgetCache(?int $filialeId = null): void
    {
        $filialeId = $filialeId ?? FilialeHelper::getCurrentFilialeId();
        Cache::forget('sig_parametres_current_'.($filialeId ?? 'none'));
        Cache::forget('sig_parametres_current');
    }

    public function fondsPropresReference(): ?float
    {
        if ($this->fonds_propres === null) {
            return null;
        }

        $fp = (float) $this->fonds_propres;

        return $fp > 0 ? $fp : null;
    }

    public function seuilTauxPct(): float
    {
        $v = (float) $this->seuil_taux_pct;

        return $v > 0 ? $v : (float) config('sig.encours_taux_seuil_pct', 10);
    }

    public function alerteTauxPct(): float
    {
        $v = (float) $this->alerte_taux_pct;

        return $v > 0 ? $v : (float) config('sig.encours_taux_alerte_pct', 8);
    }

    /**
     * @return 'conforme'|'alerte'|'depassement'|'non_evalue'
     */
    public function statutConformitePourRatio(?float $ratioPct): string
    {
        if ($ratioPct === null) {
            return 'non_evalue';
        }

        $seuil = $this->seuilTauxPct();
        $alerte = $this->alerteTauxPct();

        if ($ratioPct > $seuil) {
            return 'depassement';
        }
        if ($ratioPct >= $alerte) {
            return 'alerte';
        }

        return 'conforme';
    }

    /**
     * @return array{fonds_propres: float|null, seuil_taux_pct: float, alerte_taux_pct: float}
     */
    public function toVueArray(): array
    {
        return [
            'fonds_propres' => $this->fondsPropresReference(),
            'seuil_taux_pct' => $this->seuilTauxPct(),
            'alerte_taux_pct' => $this->alerteTauxPct(),
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $params): void {
            static::forgetCache($params->filiale_id ? (int) $params->filiale_id : null);
        });
        static::deleted(function (self $params): void {
            static::forgetCache($params->filiale_id ? (int) $params->filiale_id : null);
        });
    }
}
