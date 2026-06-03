<?php

namespace App\Support;

class ProfilDepartementRoleResolver
{
    /**
     * Slug de rôle à assigner selon le département du profil (import Excel / provisioning).
     */
    public static function resolve(?string $departement): ?string
    {
        if ($departement === null || trim($departement) === '') {
            return null;
        }

        $key = self::normalizeDepartementKey($departement);

        /** @var array<string, string> $map */
        $map = config('cofina.departement_role_map', self::defaultMap());

        if (isset($map[$key])) {
            return $map[$key];
        }

        return (string) config('cofina.default_departement_role', 'metier');
    }

    /**
     * Même normalisation que l'import Excel (ProfilBulkImportService::resolveDepartement).
     */
    public static function normalizeDepartementKey(string $departement): string
    {
        $normalized = preg_replace('/informatique/i', 'IT', $departement);
        $normalized = preg_replace('/^direction\s+/i', '', (string) $normalized);
        $normalized = preg_replace('/^departement\s+/i', '', (string) $normalized);
        if (preg_match('/exploitation/i', (string) $normalized)) {
            $normalized = 'EXPLOITATION';
        }
        $key = mb_strtoupper(trim((string) $normalized), 'UTF-8');

        return str_replace(
            ['É', 'È', 'Ê', 'Ë', 'À', 'Â', 'Ç', 'Ô', 'Ö', 'Û', 'Ü', 'Î', 'Ï', 'Ù', 'Ú'],
            ['E', 'E', 'E', 'E', 'A', 'A', 'C', 'O', 'O', 'U', 'U', 'I', 'I', 'U', 'U'],
            $key
        );
    }

    /**
     * @return array<string, string>
     */
    public static function defaultMap(): array
    {
        return [
            'IT' => 'admin',
            'RH' => 'rh',
            'RESSOURCES HUMAINES' => 'rh',
            'CONTROLE' => 'controle',
            'CONFORMITE' => 'conformite',
        ];
    }
}
