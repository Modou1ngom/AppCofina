<?php

namespace App\Console\Commands;

use App\Models\Profil;
use App\Services\ProfilUserProvisioningService;
use Illuminate\Console\Command;

class ProvisionProfilUsers extends Command
{
    protected $signature = 'profils:provision-users
                            {--force : Sans confirmation}';

    protected $description = 'Crée les comptes utilisateurs pour les profils ayant un e-mail';

    public function handle(ProfilUserProvisioningService $provisioning): int
    {
        if (! $provisioning->isEnabled()) {
            $this->warn('COFINA_PROVISION_USER_ON_PROFIL=false : provisioning désactivé.');

            return self::SUCCESS;
        }

        $profils = Profil::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->get();

        if ($profils->isEmpty()) {
            $this->info('Aucun profil avec e-mail.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Créer/mettre à jour les comptes pour {$profils->count()} profil(s) ?")) {
            return self::SUCCESS;
        }

        $this->info('Provisioning en cours (mot de passe hashé une seule fois)...');
        $count = $provisioning->provisionMany($profils);
        $this->info("{$count} compte(s) utilisateur traité(s).");

        return self::SUCCESS;
    }
}
