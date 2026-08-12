<?php

namespace App\Helpers;

use App\Models\Filiale;
use Illuminate\Support\Str;

class FilialeHelper
{
    /**
     * Récupère l'ID de la filiale actuelle depuis la session
     */
    public static function getCurrentFilialeId(): ?int
    {
        return session('current_filiale_id');
    }

    /**
     * Définit la filiale actuelle dans la session
     */
    public static function setCurrentFilialeId(?int $filialeId): void
    {
        session(['current_filiale_id' => $filialeId]);
    }

    /**
     * Préfixe Oracle (SN, TG, CG, …) pour l'environnement / filiale courant.
     */
    public static function oraclePaysPrefix(?int $filialeId = null): string
    {
        $default = strtoupper((string) config('sig_oracle_report_groupe.pays.default_prefix', 'SN'));
        $map = self::filialePrefixMap();

        $filialeId = $filialeId ?? self::getCurrentFilialeId();
        if (! $filialeId) {
            return $default;
        }

        $nom = Filiale::query()->whereKey($filialeId)->value('nom');
        if (! is_string($nom) || trim($nom) === '') {
            return $default;
        }

        return self::resolvePaysPrefixFromName($nom, $default, $map);
    }

    /**
     * Résout un préfixe Oracle à partir du nom d'environnement / filiale.
     *
     * @param  array<string, string>  $map
     */
    public static function resolvePaysPrefixFromName(string $nom, ?string $default = null, ?array $map = null): string
    {
        $default = strtoupper($default ?? (string) config('sig_oracle_report_groupe.pays.default_prefix', 'SN'));
        $map ??= self::filialePrefixMap();

        $key = self::normalizeFilialeKey($nom);
        if ($key === '') {
            return $default;
        }

        // Code déjà court (SN, TG, CG, …)
        if (preg_match('/^[a-z]{2,3}$/', $key) === 1 && isset($map[$key])) {
            return strtoupper($map[$key]);
        }
        if (preg_match('/^[a-z]{2,3}$/', $key) === 1) {
            return strtoupper($key);
        }

        if (isset($map[$key]) && $map[$key] !== '') {
            return strtoupper($map[$key]);
        }

        // Correspondance exacte après normalisation des séparateurs
        $compact = str_replace([' ', '-', '_', '\''], '', $key);
        foreach ($map as $alias => $prefix) {
            if (! is_string($alias) || ! is_string($prefix) || $prefix === '') {
                continue;
            }
            $aliasCompact = str_replace([' ', '-', '_', '\''], '', self::normalizeFilialeKey($alias));
            if ($aliasCompact !== '' && $aliasCompact === $compact) {
                return strtoupper($prefix);
            }
        }

        // Contient un alias long (≥ 4 car. pour limiter les faux positifs)
        // Priorité aux alias les plus longs (ex. "congo brazzaville" avant "congo")
        $candidates = [];
        foreach ($map as $alias => $prefix) {
            if (! is_string($alias) || ! is_string($prefix) || $prefix === '') {
                continue;
            }
            $aliasKey = self::normalizeFilialeKey($alias);
            if (strlen($aliasKey) < 4) {
                continue;
            }
            if (str_contains($key, $aliasKey)) {
                $candidates[strlen($aliasKey)] = strtoupper($prefix);
            }
        }
        if ($candidates !== []) {
            krsort($candidates);

            return (string) reset($candidates);
        }

        return $default;
    }

    /**
     * @return array<string, string>
     */
    private static function filialePrefixMap(): array
    {
        $map = config('sig_oracle_report_groupe.pays.filiale_prefixes', []);
        if (! is_array($map)) {
            return [];
        }

        $out = [];
        foreach ($map as $alias => $prefix) {
            if (! is_string($alias) || ! is_string($prefix) || $prefix === '') {
                continue;
            }
            $out[self::normalizeFilialeKey($alias)] = strtoupper($prefix);
        }

        return $out;
    }

    private static function normalizeFilialeKey(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    /**
     * Filtre une requête par la filiale actuelle
     */
    public static function scopeForCurrentFiliale($query, string $column = 'filiale_id')
    {
        $filialeId = self::getCurrentFilialeId();

        if ($filialeId) {
            return $query->where($column, $filialeId);
        }

        return $query;
    }

    /**
     * Filtre les habilitations par filiale via les profils
     */
    public static function scopeHabilitationsForCurrentFiliale($query)
    {
        $filialeId = self::getCurrentFilialeId();

        if ($filialeId) {
            return $query->whereHas('requester', function ($q) use ($filialeId) {
                $q->where('filiale_id', $filialeId);
            })->orWhereHas('beneficiary', function ($q) use ($filialeId) {
                $q->where('filiale_id', $filialeId);
            });
        }

        return $query;
    }
}
