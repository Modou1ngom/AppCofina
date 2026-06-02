<?php

namespace App\Console\Commands;

use App\Models\Profil;
use App\Services\ProfilUserProvisioningService;
use App\Support\ProfilExcelImport;
use Illuminate\Console\Command;

class SyncProfilEmailsFromExcel extends Command
{
    protected $signature = 'profils:sync-emails
                            {file : Chemin vers le fichier .xlsx / .xls}
                            {--force : Sans confirmation}
                            {--provision : Créer aussi les comptes utilisateurs manquants}';

    protected $description = 'Met à jour les e-mails des profils depuis un fichier Excel (colonne Email, matricule Matricul/Matricule)';

    public function handle(ProfilUserProvisioningService $provisioning): int
    {
        $path = $this->argument('file');
        if (! is_readable($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        $allRows = ProfilExcelImport::readRowsFromPath($path);
        if ($allRows === []) {
            $this->error('Fichier vide.');

            return self::FAILURE;
        }

        $headerIndex = ProfilExcelImport::detectHeaderRowIndex($allRows);
        $header = $allRows[$headerIndex];
        $rows = array_slice($allRows, $headerIndex + 1);
        $mapped = ProfilExcelImport::mapColumns($header);
        $mapped = ProfilExcelImport::refineEmailColumnMapping($mapped, $header, $rows);

        if (! isset($mapped['nom'], $mapped['prenom'])) {
            $this->error('Colonnes Nom et Prénom requises.');

            return self::FAILURE;
        }

        $this->info('Colonnes : '.json_encode($mapped, JSON_UNESCAPED_UNICODE));

        if (! $this->option('force') && ! $this->confirm('Mettre à jour les e-mails des profils correspondants ?')) {
            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $notFound = 0;
        $profilsToProvision = [];

        foreach ($rows as $row) {
            if ($row === [] || ! array_filter($row, static fn ($v) => ProfilExcelImport::cellToString($v) !== '')) {
                continue;
            }

            $matricule = isset($mapped['matricule'])
                ? ProfilExcelImport::cellToString($row[$mapped['matricule']] ?? '')
                : '';
            $prenom = ProfilExcelImport::cellToString($row[$mapped['prenom']] ?? '');
            $nom = ProfilExcelImport::cellToString($row[$mapped['nom']] ?? '');

            if ($matricule === '' && ($nom === '' || $prenom === '')) {
                continue;
            }

            $exclude = array_values(array_filter([
                $mapped['nom'] ?? null,
                $mapped['prenom'] ?? null,
                $mapped['telephone'] ?? null,
            ], static fn ($i) => $i !== null));

            [$email] = ProfilExcelImport::resolveEmailWithSource(
                $row,
                $mapped,
                $prenom,
                $nom,
                $matricule !== '' ? $matricule : null,
                $exclude
            );

            if ($email === null) {
                $skipped++;

                continue;
            }

            $profil = $matricule !== ''
                ? Profil::query()->where('matricule', $matricule)->first()
                : Profil::query()
                    ->whereRaw('LOWER(TRIM(nom)) = ?', [mb_strtolower($nom)])
                    ->whereRaw('LOWER(TRIM(prenom)) = ?', [mb_strtolower($prenom)])
                    ->first();

            if ($profil === null) {
                $notFound++;

                continue;
            }

            if (strtolower(trim((string) $profil->email)) === $email) {
                continue;
            }

            $profil->update(['email' => $email]);
            $updated++;
            $profilsToProvision[] = $profil->fresh();
        }

        $provisioned = 0;
        if ($this->option('provision') && $profilsToProvision !== []) {
            $provisioned = $provisioning->provisionMany($profilsToProvision);
        }

        $this->info("{$updated} e-mail(s) mis à jour, {$skipped} ligne(s) sans e-mail, {$notFound} profil(s) introuvable(s).");
        if ($provisioned > 0) {
            $this->info("{$provisioned} compte(s) utilisateur créé(s) ou synchronisé(s).");
        }

        return self::SUCCESS;
    }
}
