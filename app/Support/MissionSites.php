<?php

namespace App\Support;

class MissionSites
{
    /**
     * @return array{national: array<int, string>, international: array<int, string>}
     */
    public static function catalog(): array
    {
        return [
            'national' => config('mission_sites.national', []),
            'international' => config('mission_sites.international', []),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allLabels(): array
    {
        return array_values(array_merge(
            config('mission_sites.national', []),
            config('mission_sites.international', []),
        ));
    }

    /**
     * @param  array<int, string>  $sites
     * @return array<int, string>
     */
    public static function validerSelection(array $sites): array
    {
        $autorises = self::allLabels();

        return array_values(array_unique(array_filter(
            $sites,
            fn (string $site) => in_array($site, $autorises, true),
        )));
    }

    /**
     * @param  array<int, string>  $sites
     */
    public static function perimetreDepuisSites(array $sites): string
    {
        return implode(', ', self::validerSelection($sites));
    }

    /**
     * @return array<int, string>
     */
    public static function extraireDepuisPerimetre(?string $perimetre): array
    {
        if ($perimetre === null || trim($perimetre) === '') {
            return [];
        }

        $trouves = [];
        foreach (self::allLabels() as $label) {
            if (stripos($perimetre, $label) !== false) {
                $trouves[] = $label;
            }
        }

        return $trouves;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Mission>  $query
     * @return array<int, array{site: string, count: int}>
     */
    public static function statsSitesPopulaires($query, int $limite = 8): array
    {
        $compteur = [];

        (clone $query)
            ->whereNotNull('sites_mission')
            ->select(['id', 'sites_mission'])
            ->chunkById(200, function ($missions) use (&$compteur) {
                foreach ($missions as $mission) {
                    foreach ($mission->sites_mission ?? [] as $site) {
                        $label = is_string($site) ? $site : (string) ($site['label'] ?? '');
                        if ($label === '') {
                            continue;
                        }
                        $compteur[$label] = ($compteur[$label] ?? 0) + 1;
                    }
                }
            });

        arsort($compteur);

        return collect($compteur)
            ->take($limite)
            ->map(fn (int $count, string $site) => ['site' => $site, 'count' => $count])
            ->values()
            ->all();
    }
}
