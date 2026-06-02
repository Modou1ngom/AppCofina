<?php

namespace App\Services;

use App\Models\Agence;
use App\Models\Departement;
use App\Models\Filiale;
use App\Models\Profil;
use App\Models\User;
use App\Support\ProfilExcelImport;

class ProfilBulkImportService
{
    /** @var array<string, true> */
    private array $knownMatricules = [];

    /** @var array<string, true> */
    private array $knownEmails = [];

    /** @var array<string, int> */
    private array $profilIdByMatricule = [];

    /** @var array<string, int> */
    private array $profilIdByEmail = [];

    /** @var array<string, int> */
    private array $profilIdByName = [];

    /** @var array<string, string> */
    private array $departementCache = [];

    /** @var array<string, \App\Models\Agence> */
    private array $agenceCache = [];

    private int $nextMatriculeNum = 0;

    private const MATRICULE_PREFIX = 'M0';

    /**
     * @param  list<list<string>>  $rows
     * @param  array<string, int>  $mappedColumns
     * @return array{
     *     imported: int,
     *     updated: int,
     *     created: int,
     *     skipped: int,
     *     imported_with_email: int,
     *     emails_from_file: int,
     *     emails_generated: int,
     *     errors: list<string>,
     *     created_profils: list<Profil>
     * }
     */
    public function process(array $rows, array $mappedColumns, int $headerIndex, ?User $user): array
    {
        $this->bootstrapLookups();

        $filialeSenegal = Filiale::firstOrCreate(
            ['nom' => 'Sénégal'],
            ['description' => 'Filiale Sénégal', 'actif' => true]
        );

        $imported = 0;
        $created = 0;
        $updated = 0;
        $importedWithEmail = 0;
        $emailsFromFile = 0;
        $emailsGenerated = 0;
        $skipped = 0;
        $errors = [];
        $createdProfils = [];

        foreach ($rows as $rowIndex => $row) {
            if ($row === [] || ! array_filter($row, static fn ($v) => ProfilExcelImport::cellToString($v) !== '')) {
                continue;
            }

            $line = $rowIndex + $headerIndex + 2;
            $nom = ProfilExcelImport::cellToString($row[$mappedColumns['nom']] ?? '');
            $prenom = ProfilExcelImport::cellToString($row[$mappedColumns['prenom']] ?? '');

            if ($nom === '' || $prenom === '') {
                $skipped++;

                continue;
            }

            $matricule = isset($mappedColumns['matricule'])
                ? ProfilExcelImport::cellToString($row[$mappedColumns['matricule']] ?? '')
                : '';
            $matricule = $matricule !== '' ? $matricule : null;

            $excludeFromEmailScan = array_values(array_filter([
                $mappedColumns['nom'] ?? null,
                $mappedColumns['prenom'] ?? null,
                $mappedColumns['matricule'] ?? null,
                $mappedColumns['telephone'] ?? null,
            ], static fn ($i) => $i !== null));

            [$email, $emailSource] = ProfilExcelImport::resolveEmailWithSource(
                $row,
                $mappedColumns,
                $prenom,
                $nom,
                $matricule,
                $excludeFromEmailScan
            );

            if ($matricule === null) {
                $matricule = $this->nextAutoMatricule();
            }

            $matriculeKey = strtoupper($matricule);
            $existingProfilId = $this->profilIdByMatricule[$matriculeKey] ?? null;
            $existingProfil = $existingProfilId ? Profil::query()->find($existingProfilId) : null;

            if ($email !== null) {
                $emailOwnerId = $this->profilIdByEmail[$email] ?? null;
                if ($emailOwnerId !== null && ($existingProfil === null || (int) $emailOwnerId !== (int) $existingProfil->id)) {
                    $skipped++;
                    $errors[] = "Ligne {$line}: Email déjà utilisé par un autre profil ({$email})";

                    continue;
                }
                $importedWithEmail++;
                if ($emailSource === 'generated') {
                    $emailsGenerated++;
                } else {
                    $emailsFromFile++;
                }
            }

            $typeContrat = $this->resolveTypeContrat($row, $mappedColumns, $line, $errors);
            $statut = $this->resolveStatut($row, $mappedColumns);
            $typeOffice = $this->resolveTypeOffice($row, $mappedColumns);

            $departement = isset($mappedColumns['departement'])
                ? $this->resolveDepartement(ProfilExcelImport::cellToString($row[$mappedColumns['departement']] ?? ''))
                : null;

            $site = isset($mappedColumns['site'])
                ? $this->resolveAgence(ProfilExcelImport::cellToString($row[$mappedColumns['site']] ?? ''), $filialeSenegal->id)
                : null;

            [$nPlus1Id, $nPlus2Id, $n1Error] = $this->resolveNPlus1(
                isset($mappedColumns['n_plus_1']) ? ProfilExcelImport::cellToString($row[$mappedColumns['n_plus_1']] ?? '') : '',
                $prenom,
                $nom,
                $matricule
            );
            if ($n1Error !== null) {
                $errors[] = "Ligne {$line}: {$n1Error}";
            }

            $attributes = [
                'nom' => $nom,
                'prenom' => $prenom,
                'matricule' => $matricule,
                'telephone' => $this->optionalCell($row, $mappedColumns, 'telephone'),
                'fonction' => $this->optionalCell($row, $mappedColumns, 'fonction'),
                'departement' => $departement,
                'site' => $site,
                'numero_compte' => $this->optionalCell($row, $mappedColumns, 'numero_compte'),
                'code_agence' => $this->optionalCell($row, $mappedColumns, 'code_agence'),
                'type_contrat' => $typeContrat,
                'statut' => $statut,
                'statut_rh' => $this->optionalCell($row, $mappedColumns, 'statut_rh'),
                'type_office' => $typeOffice,
                'n_plus_1_id' => $nPlus1Id,
                'n_plus_2_id' => $nPlus2Id,
                'filiale_id' => $filialeSenegal->id,
            ];

            if ($email !== null) {
                $attributes['email'] = $email;
            }

            if ($existingProfil !== null) {
                $existingProfil->update($attributes);
                $profil = $existingProfil->fresh();
                $updated++;
            } else {
                $profil = Profil::create($attributes);
                $created++;
            }

            $this->rememberProfil($profil);
            $createdProfils[] = $profil;
            $imported++;
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'created' => $created,
            'skipped' => $skipped,
            'imported_with_email' => $importedWithEmail,
            'emails_from_file' => $emailsFromFile,
            'emails_generated' => $emailsGenerated,
            'errors' => $errors,
            'created_profils' => $createdProfils,
        ];
    }

    private function bootstrapLookups(): void
    {
        $this->knownMatricules = [];
        $this->knownEmails = [];
        $this->profilIdByMatricule = [];
        $this->profilIdByEmail = [];
        $this->profilIdByName = [];
        $this->nextMatriculeNum = 0;

        foreach (Profil::query()->select('id', 'matricule', 'email', 'prenom', 'nom', 'n_plus_1_id')->cursor() as $profil) {
            $this->rememberProfil($profil, false);
            $number = $this->extractMatriculeNumber((string) $profil->matricule);
            if ($number > $this->nextMatriculeNum) {
                $this->nextMatriculeNum = $number;
            }
        }
    }

    private function rememberProfil(Profil $profil, bool $trackMatricule = true): void
    {
        $matriculeKey = strtoupper(trim((string) $profil->matricule));
        if ($matriculeKey !== '') {
            $this->profilIdByMatricule[$matriculeKey] = (int) $profil->id;
            if ($trackMatricule) {
                $this->knownMatricules[$matriculeKey] = true;
            }
        }

        $email = strtolower(trim((string) $profil->email));
        if ($email !== '') {
            $this->profilIdByEmail[$email] = (int) $profil->id;
            if ($trackMatricule) {
                $this->knownEmails[$email] = true;
            }
        }

        $nameKey = $this->nameKey($profil->prenom, $profil->nom);
        if ($nameKey !== '') {
            $this->profilIdByName[$nameKey] = (int) $profil->id;
        }
    }

    private function nextAutoMatricule(): string
    {
        $this->nextMatriculeNum++;

        return self::MATRICULE_PREFIX.$this->nextMatriculeNum;
    }

    private function extractMatriculeNumber(string $matricule): int
    {
        $numberPart = substr($matricule, 1);
        if (preg_match('/-(\d+)$/', $numberPart, $matches)) {
            return (int) $matches[1];
        }
        if (preg_match('/^(\d+)/', $numberPart, $matches)) {
            return (int) $matches[1];
        }

        return (int) preg_replace('/[^0-9]/', '', $numberPart);
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $mappedColumns
     */
    private function optionalCell(array $row, array $mappedColumns, string $key): ?string
    {
        if (! isset($mappedColumns[$key])) {
            return null;
        }
        $value = ProfilExcelImport::cellToString($row[$mappedColumns[$key]] ?? '');

        return $value !== '' ? $value : null;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $mappedColumns
     * @param  list<string>  $errors
     */
    private function resolveTypeContrat(array $row, array $mappedColumns, int $line, array &$errors): string
    {
        $hasColumn = isset($mappedColumns['type_contrat']);
        $raw = $hasColumn ? ($row[$mappedColumns['type_contrat']] ?? null) : null;
        $type = ProfilExcelImport::normalizeTypeContrat($raw);

        if ($type === null && $hasColumn && ProfilExcelImport::cellToString($raw) !== '') {
            $errors[] = "Ligne {$line}: Type de contrat non reconnu, CDI appliqué.";
            $type = 'CDI';
        }

        return $type ?? 'CDI';
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $mappedColumns
     */
    private function resolveStatut(array $row, array $mappedColumns): string
    {
        $statut = isset($mappedColumns['statut'])
            ? ProfilExcelImport::cellToString($row[$mappedColumns['statut']] ?? '')
            : 'actif';

        return in_array(strtolower($statut), ['actif', 'inactif'], true) ? strtolower($statut) : 'actif';
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $mappedColumns
     */
    private function resolveTypeOffice(array $row, array $mappedColumns): ?string
    {
        $typeOffice = isset($mappedColumns['type_office'])
            ? ProfilExcelImport::cellToString($row[$mappedColumns['type_office']] ?? '')
            : '';

        if ($typeOffice === '') {
            return null;
        }

        if (stripos($typeOffice, 'back') !== false) {
            return 'Back Office';
        }
        if (stripos($typeOffice, 'front') !== false) {
            return 'Front Office';
        }

        return in_array($typeOffice, ['Back Office', 'Front Office'], true) ? $typeOffice : null;
    }

    private function resolveDepartement(string $departement): ?string
    {
        $departement = trim($departement);
        if ($departement === '') {
            return null;
        }

        $normalized = preg_replace('/informatique/i', 'IT', $departement);
        $normalized = preg_replace('/^direction\s+/i', '', (string) $normalized);
        if (preg_match('/exploitation/i', (string) $normalized)) {
            $normalized = 'EXPLOITATION';
        }
        $normalized = strtoupper(trim((string) $normalized));

        if (isset($this->departementCache[$normalized])) {
            return $this->departementCache[$normalized];
        }

        $departementModel = Departement::query()
            ->whereRaw('UPPER(TRIM(nom)) = ?', [$normalized])
            ->first();

        if ($departementModel === null) {
            $departementModel = Departement::create([
                'nom' => $normalized,
                'description' => 'Direction '.strtolower($normalized),
                'actif' => true,
            ]);
        }

        $this->departementCache[$normalized] = $departementModel->nom;

        return $departementModel->nom;
    }

    private function resolveAgence(string $site, int $filialeId): ?string
    {
        $site = trim($site);
        if ($site === '') {
            return null;
        }

        $key = strtoupper($site);
        if (isset($this->agenceCache[$key])) {
            return $this->agenceCache[$key]->nom;
        }

        $agence = Agence::firstOrCreate(
            ['nom' => $site],
            [
                'description' => 'Agence '.$site,
                'actif' => true,
                'filiale_id' => $filialeId,
            ]
        );

        if (! $agence->filiale_id) {
            $agence->update(['filiale_id' => $filialeId]);
        }

        $this->agenceCache[$key] = $agence;

        return $agence->nom;
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: ?string}
     */
    private function resolveNPlus1(string $value, string $prenom, string $nom, ?string $matricule): array
    {
        $value = trim($value);
        if ($value === '') {
            return [null, null, null];
        }

        $nPlus1Id = null;

        $matriculeKey = strtoupper($value);
        if (isset($this->profilIdByMatricule[$matriculeKey])) {
            $nPlus1Id = $this->profilIdByMatricule[$matriculeKey];
        }

        if ($nPlus1Id === null) {
            $email = strtolower($value);
            if (isset($this->profilIdByEmail[$email])) {
                $nPlus1Id = $this->profilIdByEmail[$email];
            }
        }

        if ($nPlus1Id === null) {
            $parts = preg_split('/\s+/', $value) ?: [];
            if (count($parts) >= 2) {
                $nPlus1Id = $this->profilIdByName[$this->nameKey($parts[0], $parts[count($parts) - 1])] ?? null;
                if ($nPlus1Id === null && count($parts) === 2) {
                    $nPlus1Id = $this->profilIdByName[$this->nameKey($parts[1], $parts[0])] ?? null;
                }
            }
        }

        if ($nPlus1Id === null) {
            return [null, null, "N+1 non trouvé ({$value}) pour {$prenom} {$nom}."];
        }

        $nPlus1 = Profil::query()->select('id', 'n_plus_1_id', 'matricule', 'prenom', 'nom')->find($nPlus1Id);
        if ($nPlus1 === null) {
            return [null, null, null];
        }

        if (($matricule !== null && $nPlus1->matricule === $matricule)
            || (strcasecmp($nPlus1->prenom, $prenom) === 0 && strcasecmp($nPlus1->nom, $nom) === 0)) {
            return [null, null, "Le N+1 ({$value}) correspond au profil en cours pour {$prenom} {$nom}."];
        }

        $nPlus2Id = ($nPlus1->n_plus_1_id && (int) $nPlus1->n_plus_1_id !== (int) $nPlus1Id)
            ? (int) $nPlus1->n_plus_1_id
            : null;

        return [$nPlus1Id, $nPlus2Id, null];
    }

    private function nameKey(string $prenom, string $nom): string
    {
        return mb_strtolower(trim($prenom), 'UTF-8').'|'.mb_strtolower(trim($nom), 'UTF-8');
    }
}
