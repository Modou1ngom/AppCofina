<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProfilExcelImport
{
    /** @var array<string, list<string>> */
    public const COLUMN_ALIASES = [
        'nom' => ['nom', 'name', 'lastname', 'last_name'],
        'prenom' => ['prenom', 'prénom', 'firstname', 'first_name'],
        'matricule' => ['matricule', 'matricul', 'mat', 'employee_id', 'id employe', 'id employé'],
        'email' => [
            'email',
            'e-mail',
            'e mail',
            'adresse email',
            'adresse e-mail',
            'adresse mail',
            'adresse electronique',
            'adresse électronique',
            'courriel',
            'email professionnel',
            'email professionnelle',
            'email pro',
            'email personnel',
            'mail professionnel',
            'mail pro',
            'e_mail',
            'adresse e mail',
            'messagerie',
            'messagerie electronique',
            'compte mail',
            'upn',
            'user principal name',
        ],
        'login' => [
            'login',
            'login ad',
            'login windows',
            'identifiant reseau',
            'identifiant réseau',
            'compte ad',
            'compte windows',
            'user login',
            'nom utilisateur',
            'samaccountname',
            'account name',
        ],
        'telephone' => ['telephone', 'téléphone', 'tel', 'phone', 'mobile', 'gsm'],
        'fonction' => ['fonction', 'function', 'poste', 'job', 'position', 'intitule poste', 'intitulé poste'],
        'departement' => ['departement', 'département', 'department', 'dept', 'direction'],
        'site' => ['site', 'agence', 'agency', 'location', 'lieu'],
        'numero_compte' => ['numero de compte', 'numéro de compte', 'numero_compte', 'num_compte', 'compte', 'n° compte'],
        'code_agence' => ['code agence', 'code_agence', 'agence code', 'branch_code', 'code agence'],
        'type_contrat' => [
            'type_contrat',
            'type contrat',
            'type de contrat',
            'type du contrat',
            'contract_type',
            'contrat',
            'nature contrat',
            'nature du contrat',
        ],
        'statut' => ['status', 'etat', 'état', 'statut actif', 'statut collaborateur'],
        'statut_rh' => ['statut rh', 'statut_rh', 'classification statut', 'statut rh'],
        'type_office' => [
            'type_office',
            'type office',
            'back front office',
            'back/front office',
            'office',
            'back office',
            'front office',
            'back/front',
        ],
        'n_plus_1' => [
            'n+1',
            'n_plus_1',
            'n plus 1',
            'superieur',
            'supérieur',
            'superieur hierarchique',
            'supérieur hierarchique',
            'superieur_hierarchique',
            'manager',
            'responsable',
            'n+1 (nom prenom)',
            'n+1 (nom prénom)',
        ],
    ];

    /**
     * @param  list<mixed>  $headerRow
     * @return array<string, int>
     */
    public static function mapColumns(array $headerRow): array
    {
        $headerMap = [];
        foreach ($headerRow as $index => $col) {
            $key = self::normalizeHeaderKey($col);
            if ($key !== '') {
                $headerMap[$key] = (int) $index;
            }
        }

        $mapped = [];
        foreach (self::COLUMN_ALIASES as $dbColumn => $aliases) {
            foreach ($aliases as $alias) {
                $key = self::normalizeHeaderKey($alias);
                if (isset($headerMap[$key])) {
                    $mapped[$dbColumn] = $headerMap[$key];
                    break;
                }
            }
        }

        // Correspondance partielle (ex. "Type de contrat (RH)")
        $partialRules = [
            'email' => ['email', 'e-mail', 'e mail', 'courriel', 'adresse mail', 'messagerie', 'electronique', 'upn', 'principal name'],
            'login' => ['login', 'identifiant', 'compte ad', 'samaccount', 'utilisateur'],
            'type_contrat' => ['type contrat', 'type de contrat', 'nature contrat'],
            'telephone' => ['telephone', 'téléphone', 'tel', 'mobile'],
        ];

        foreach ($partialRules as $dbColumn => $needles) {
            if (isset($mapped[$dbColumn])) {
                continue;
            }
            foreach ($headerMap as $headerKey => $index) {
                foreach ($needles as $needle) {
                    if (str_contains($headerKey, self::normalizeHeaderKey($needle))) {
                        $mapped[$dbColumn] = $index;
                        break 2;
                    }
                }
            }
        }

        return $mapped;
    }

    /**
     * Détecte la colonne qui contient le plus d'adresses e-mail (analyse des lignes données).
     *
     * @param  list<list<string>>  $sampleRows
     * @param  list<int>  $excludeIndexes
     */
    public static function inferEmailColumnIndex(array $sampleRows, array $excludeIndexes = []): ?int
    {
        $maxIndex = 0;
        foreach ($sampleRows as $row) {
            $maxIndex = max($maxIndex, count($row) - 1);
        }

        $bestIndex = null;
        $bestScore = 0;

        for ($index = 0; $index <= $maxIndex; $index++) {
            if (in_array($index, $excludeIndexes, true)) {
                continue;
            }

            $score = self::scoreColumnAsEmail($sampleRows, $index);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        return $bestScore >= 1 ? $bestIndex : null;
    }

    /**
     * @param  list<list<string>>  $sampleRows
     */
    public static function scoreColumnAsEmail(array $sampleRows, ?int $columnIndex): int
    {
        if ($columnIndex === null) {
            return 0;
        }

        $score = 0;
        foreach ($sampleRows as $row) {
            if (self::normalizeEmail($row[$columnIndex] ?? null) !== null) {
                $score++;
            }
        }

        return $score;
    }

    /**
     * @param  array<string, int>  $mapped
     * @param  list<mixed>  $headerRow
     * @param  list<list<string>>  $dataRows
     * @return array<string, int>
     */
    public static function refineEmailColumnMapping(array $mapped, array $headerRow, array $dataRows): array
    {
        $sample = array_slice($dataRows, 0, 40);
        $exclude = array_values(array_filter([
            $mapped['nom'] ?? null,
            $mapped['prenom'] ?? null,
            $mapped['matricule'] ?? null,
            $mapped['telephone'] ?? null,
        ], static fn ($i) => $i !== null));

        $inferred = self::inferEmailColumnIndex($sample, $exclude);
        if ($inferred === null) {
            return $mapped;
        }

        $currentScore = self::scoreColumnAsEmail($sample, $mapped['email'] ?? null);
        $inferredScore = self::scoreColumnAsEmail($sample, $inferred);

        if ($inferredScore > $currentScore) {
            $mapped['email'] = $inferred;
        }

        return $mapped;
    }

    /**
     * Lit la première feuille (valeurs calculées, hyperlinks mailto, texte enrichi).
     *
     * @return list<list<string>>
     */
    public static function readRowsFromPath(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        return self::readRowsFromWorksheet($sheet);
    }

    /**
     * @return list<list<string>>
     */
    public static function readRowsFromWorksheet(Worksheet $sheet): array
    {
        $rows = [];
        $highestRow = $sheet->getHighestDataRow();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
            $sheet->getHighestDataColumn()
        );

        for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
            $rowData = [];
            $hasValue = false;

            for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
                $cell = $sheet->getCellByColumnAndRow($colIndex, $rowIndex);
                $value = self::extractCellValue($cell);
                $rowData[] = $value;
                if ($value !== '') {
                    $hasValue = true;
                }
            }

            while ($rowData !== [] && end($rowData) === '') {
                array_pop($rowData);
            }

            if ($hasValue) {
                $rows[] = $rowData;
            }
        }

        return $rows;
    }

    /**
     * Détecte la ligne d'en-têtes (les 8 premières lignes).
     *
     * @param  list<list<string>>  $rows
     */
    public static function detectHeaderRowIndex(array $rows): int
    {
        foreach (array_slice($rows, 0, 8) as $index => $row) {
            $mapped = self::mapColumns($row);
            if (isset($mapped['nom'], $mapped['prenom'])) {
                return $index;
            }
        }

        return 0;
    }

    public static function extractCellValue(Cell $cell): string
    {
        $value = $cell->getCalculatedValue();

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        $str = self::cellToString($value);
        if ($str !== '') {
            return $str;
        }

        $hyperlink = $cell->getHyperlink();
        if ($hyperlink !== null) {
            $url = trim((string) $hyperlink->getUrl());
            if ($url !== '') {
                if (str_starts_with(strtolower($url), 'mailto:')) {
                    return trim(substr($url, 7));
                }

                if (filter_var($url, FILTER_VALIDATE_EMAIL)) {
                    return $url;
                }
            }
        }

        $formatted = $cell->getFormattedValue();
        if ($formatted instanceof RichText) {
            $formatted = $formatted->getPlainText();
        }

        return self::cellToString($formatted);
    }

    /**
     * @param  list<mixed>  $row
     * @param  list<int>  $excludeIndexes
     */
    public static function findEmailInRow(array $row, ?int $preferredIndex = null, array $excludeIndexes = []): ?string
    {
        if ($preferredIndex !== null && ! in_array($preferredIndex, $excludeIndexes, true)) {
            $email = self::normalizeEmail($row[$preferredIndex] ?? null);
            if ($email !== null) {
                return $email;
            }
        }

        foreach ($row as $index => $cell) {
            if (in_array((int) $index, $excludeIndexes, true)) {
                continue;
            }
            $email = self::normalizeEmail($cell);
            if ($email !== null) {
                return $email;
            }
        }

        return null;
    }

    /**
     * Résout l'e-mail d'une ligne : cellule avec @, login AD, puis prénom.nom@domaine.
     *
     * @param  list<mixed>  $row
     * @param  array<string, int>  $mappedColumns
     * @param  list<int>  $excludeIndexes
     */
    /**
     * @return array{0: ?string, 1: 'excel'|'login'|'generated'|null}
     */
    public static function resolveEmailWithSource(
        array $row,
        array $mappedColumns,
        string $prenom,
        string $nom,
        ?string $matricule = null,
        array $excludeIndexes = []
    ): array {
        $email = self::findEmailInRow($row, $mappedColumns['email'] ?? null, $excludeIndexes);
        if ($email !== null) {
            return [$email, 'excel'];
        }

        $email = self::findEmailInRow($row, null, $excludeIndexes);
        if ($email !== null) {
            return [$email, 'excel'];
        }

        if (isset($mappedColumns['login'])) {
            $email = self::emailFromLogin($row[$mappedColumns['login']] ?? null);
            if ($email !== null) {
                return [$email, 'login'];
            }
        }

        if (config('cofina.import.generate_email_from_name', false)) {
            return [self::generateEmailFromName($prenom, $nom), 'generated'];
        }

        return [null, null];
    }

    public static function resolveEmail(
        array $row,
        array $mappedColumns,
        string $prenom,
        string $nom,
        ?string $matricule = null,
        array $excludeIndexes = []
    ): ?string {
        return self::resolveEmailWithSource($row, $mappedColumns, $prenom, $nom, $matricule, $excludeIndexes)[0];
    }

    public static function emailFromLogin(mixed $value): ?string
    {
        $raw = self::cellToString($value);
        if ($raw === '') {
            return null;
        }

        $asEmail = self::normalizeEmail($raw);
        if ($asEmail !== null) {
            return $asEmail;
        }

        $domain = ltrim((string) config('cofina.email_domain', ''), '@');
        if ($domain === '') {
            return null;
        }

        if (str_contains($raw, '\\')) {
            $user = trim((string) preg_replace('/^.*\\\\/', '', $raw));

            return $user !== '' ? strtolower($user).'@'.$domain : null;
        }

        if (! str_contains($raw, '@') && ! str_contains($raw, ' ')) {
            return strtolower($raw).'@'.$domain;
        }

        return null;
    }

    public static function generateEmailFromName(string $prenom, string $nom, ?string $matricule = null): ?string
    {
        $domain = ltrim((string) config('cofina.email_domain', ''), '@');
        if ($domain === '') {
            return null;
        }

        $local = self::slugEmailPart($prenom).'.'.self::slugEmailPart($nom);
        if ($local === '.' || $local === '') {
            return null;
        }

        return strtolower($local.'@'.$domain);
    }

    public static function slugEmailPart(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        if ($value === '') {
            return '';
        }

        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y', 'ç' => 'c', 'ñ' => 'n',
        ]);

        return (string) preg_replace('/[^a-z0-9]+/', '', $value);
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{header_index: int, mapped: array<string, int>, headers: list<string>}
     */
    public static function analyzeFile(string $path): array
    {
        $rows = self::readRowsFromPath($path);
        $headerIndex = self::detectHeaderRowIndex($rows);
        $header = $rows[$headerIndex] ?? [];

        return [
            'header_index' => $headerIndex,
            'mapped' => self::mapColumns($header),
            'headers' => array_map(static fn ($h) => self::cellToString($h), $header),
        ];
    }

    public static function normalizeHeaderKey(mixed $header): string
    {
        $value = strtolower(trim(self::cellToString($header)));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = str_replace(["\u{00A0}", "\xC2\xA0"], ' ', $value);
        $value = preg_replace('/[_\-]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? $value;

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = strtolower(trim($ascii));
        }

        return $value;
    }

    public static function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            if (is_float($value) && abs($value) >= 1_000_000) {
                return sprintf('%.0f', $value);
            }

            return trim((string) $value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            return trim(implode(' ', array_map(
                static fn ($part) => self::cellToString($part),
                $value
            )));
        }

        return trim((string) $value);
    }

    public static function normalizeEmail(mixed $value): ?string
    {
        $raw = self::cellToString($value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/<([^>\s]+@[^>\s]+)>/', $raw, $matches)) {
            $raw = $matches[1];
        }

        if (preg_match('/[\w.+\-]+@[\w.\-]+\.[a-zA-Z]{2,}/', $raw, $matches)) {
            $candidate = strtolower($matches[0]);
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        $compact = strtolower(str_replace([' ', "\u{00A0}", "\xC2\xA0"], '', $raw));

        if (filter_var($compact, FILTER_VALIDATE_EMAIL)) {
            return $compact;
        }

        foreach (preg_split('/[;,|]/', $raw) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
                return strtolower($part);
            }
        }

        return null;
    }

    /**
     * @return 'CDI'|'CDD'|'Stagiaire'|'Autre'|null
     */
    public static function normalizeTypeContrat(mixed $value): ?string
    {
        $raw = self::cellToString($value);
        if ($raw === '') {
            return null;
        }

        $allowed = ['CDI', 'CDD', 'Stagiaire', 'Autre'];
        foreach ($allowed as $type) {
            if (strcasecmp($raw, $type) === 0) {
                return $type;
            }
        }

        $upper = strtoupper(self::normalizeHeaderKey($raw));

        if (preg_match('/\bCDD\b/u', $upper)) {
            return 'CDD';
        }

        if (str_contains($upper, 'DETERMINE') && ! str_contains($upper, 'INDETERMINE')) {
            return 'CDD';
        }

        if (preg_match('/\bSTAGIAIRE\b/u', $upper) || preg_match('/\bSTAGE\b/u', $upper) || str_contains($upper, 'INTERN')) {
            return 'Stagiaire';
        }

        if (preg_match('/\bCDI\b/u', $upper) || str_contains($upper, 'INDETERMINE')) {
            return 'CDI';
        }

        if (str_contains($upper, 'AUTRE') || str_contains($upper, 'OTHER')) {
            return 'Autre';
        }

        return null;
    }
}
