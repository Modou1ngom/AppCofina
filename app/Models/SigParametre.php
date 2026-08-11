<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SigParametre extends Model
{
    protected $table = 'sig_parametres';

    protected $fillable = [
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

    public static function current(): self
    {
        return Cache::remember('sig_parametres_current', 60, function () {
            $row = static::query()->orderBy('id')->first();
            if ($row !== null) {
                return $row;
            }

            return static::query()->create([
                'fonds_propres' => null,
                'seuil_taux_pct' => (float) config('sig.encours_taux_seuil_pct', 10),
                'alerte_taux_pct' => (float) config('sig.encours_taux_alerte_pct', 8),
            ]);
        });
    }

    public static function forgetCache(): void
    {
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
        static::saved(fn () => static::forgetCache());
        static::deleted(fn () => static::forgetCache());
    }
}
