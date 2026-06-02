<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ViderTableProfils extends Command
{
    protected $signature = 'profils:vider
                            {--force : Exécuter sans confirmation}';

    protected $description = 'Supprime tous les enregistrements de la table profiles et les liaisons associées';

    public function handle(): int
    {
        if (! Schema::hasTable('profiles')) {
            $this->warn('La table profiles n\'existe pas.');

            return self::SUCCESS;
        }

        $count = (int) DB::table('profiles')->count();

        if ($count === 0) {
            $this->info('La table profiles est déjà vide.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Supprimer les {$count} profil(s) et les données liées (habilitations, rôles profil, etc.) ?")) {
            $this->comment('Opération annulée.');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            Schema::disableForeignKeyConstraints();

            if (Schema::hasTable('habilitations')) {
                DB::table('habilitations')->delete();
            }

            if (Schema::hasTable('avance_salaire_demandes')) {
                DB::table('avance_salaire_demandes')->delete();
            }

            if (Schema::hasTable('profile_role')) {
                DB::table('profile_role')->delete();
            }

            if (Schema::hasTable('departements') && Schema::hasColumn('departements', 'responsable_departement_id')) {
                DB::table('departements')->update(['responsable_departement_id' => null]);
            }

            if (Schema::hasTable('agences') && Schema::hasColumn('agences', 'chef_agence_id')) {
                DB::table('agences')->update(['chef_agence_id' => null]);
            }

            if (Schema::hasTable('filiales') && Schema::hasColumn('filiales', 'chef_filiale_id')) {
                DB::table('filiales')->update(['chef_filiale_id' => null]);
            }

            if (Schema::hasTable('sig_staffs') && Schema::hasColumn('sig_staffs', 'profile_id')) {
                DB::table('sig_staffs')->update(['profile_id' => null]);
            }

            DB::table('profiles')->update([
                'n_plus_1_id' => null,
                'n_plus_2_id' => null,
            ]);

            DB::table('profiles')->delete();

            Schema::enableForeignKeyConstraints();
        });

        $this->info("{$count} profil(s) supprimé(s). La table profiles est vide.");

        return self::SUCCESS;
    }
}
