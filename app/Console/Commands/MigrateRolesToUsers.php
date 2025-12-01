<?php

namespace App\Console\Commands;

use App\Models\Profil;
use App\Models\User;
use Illuminate\Console\Command;

class MigrateRolesToUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'roles:migrate-to-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migre les rôles des profils vers les utilisateurs correspondants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Début de la migration des rôles des profils vers les utilisateurs...');

        $profils = Profil::with('roles')->get();
        $migrated = 0;
        $skipped = 0;

        foreach ($profils as $profil) {
            if ($profil->roles->isEmpty()) {
                continue;
            }

            // Trouver l'utilisateur correspondant par email
            $user = User::where('email', $profil->email)->first();

            if (!$user) {
                $this->warn("Aucun utilisateur trouvé pour le profil {$profil->prenom} {$profil->nom} (email: {$profil->email})");
                $skipped++;
                continue;
            }

            // Récupérer les IDs des rôles du profil
            $roleIds = $profil->roles->pluck('id')->toArray();

            // Synchroniser les rôles avec l'utilisateur
            $user->roles()->syncWithoutDetaching($roleIds);

            $this->info("✓ Rôles migrés pour {$user->name} (email: {$user->email})");
            $migrated++;
        }

        $this->info("\nMigration terminée !");
        $this->info("✓ {$migrated} utilisateur(s) mis à jour");
        if ($skipped > 0) {
            $this->warn("⚠ {$skipped} profil(s) sans utilisateur correspondant");
        }

        return Command::SUCCESS;
    }
}
