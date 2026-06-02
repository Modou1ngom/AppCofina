<?php

namespace App\Console\Commands;

use App\Models\Profil;
use App\Support\ProfilExcelImport;
use Illuminate\Console\Command;

class GenererEmailsProfils extends Command
{
    protected $signature = 'profils:generer-emails
                            {--force : Sans confirmation}
                            {--dry-run : Afficher sans enregistrer}';

    protected $description = 'Génère les e-mails manquants (prenom.nom@domaine) pour les profils existants';

    public function handle(): int
    {
        $domain = ltrim((string) config('cofina.email_domain', ''), '@');
        if ($domain === '') {
            $this->error('Configurez COFINA_EMAIL_DOMAIN dans le fichier .env');

            return self::FAILURE;
        }

        $query = Profil::query()
            ->where(function ($q) {
                $q->whereNull('email')->orWhere('email', '');
            });

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('Tous les profils ont déjà un e-mail.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Générer un e-mail pour {$count} profil(s) (@{$domain}) ?")) {
            return self::SUCCESS;
        }

        $updated = 0;
        $usedEmails = Profil::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->map(static fn ($e) => strtolower(trim((string) $e)))
            ->flip();

        foreach ($query->cursor() as $profil) {
            $email = ProfilExcelImport::generateEmailFromName(
                $profil->prenom,
                $profil->nom,
                $profil->matricule
            );

            if ($email === null) {
                continue;
            }

            if ($usedEmails->has($email)) {
                $email = ProfilExcelImport::generateEmailFromName(
                    $profil->prenom,
                    $profil->nom,
                    $profil->matricule
                );
            }

            if ($usedEmails->has($email)) {
                $this->warn("Doublon ignoré : {$profil->prenom} {$profil->nom} ({$profil->matricule})");

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("{$profil->matricule} → {$email}");
                $updated++;

                continue;
            }

            $profil->update(['email' => $email]);
            $usedEmails->put($email, true);
            $updated++;
        }

        $this->info("{$updated} e-mail(s) ".($this->option('dry-run') ? 'prévus' : 'enregistrés').'.');

        return self::SUCCESS;
    }
}
