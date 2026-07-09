<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recrée les tables du module avances sur salaire après leur suppression (2026-06-01).
     */
    public function up(): void
    {
        if (Schema::hasTable('avance_salaire_demandes')) {
            return;
        }

        $files = [
            '2026_04_23_100000_add_date_entree_to_profiles_and_create_avance_salaire_demandes_table.php',
            '2026_04_23_150000_add_type_avance_and_champs_formulaire_to_avance_salaire_demandes.php',
            '2026_04_23_180000_add_rh_niveau_finance_and_cfo_md_to_avance_salaire_demandes.php',
            '2026_04_24_120000_create_avance_salaire_baremes_table.php',
            '2026_04_26_001700_add_mode_paiement_to_avance_salaire_demandes_table.php',
            '2026_04_26_003000_add_intervalle_tranche_mois_to_avance_salaire_demandes_table.php',
            '2026_04_26_120000_replace_intervalle_tranche_by_dates_tranches_on_avance_salaire_demandes.php',
            '2026_04_26_140000_add_rh_prise_en_charge_to_avance_salaire_demandes_table.php',
            '2026_04_27_100000_avance_salaire_statuts_traitement_rh.php',
            '2026_04_29_110500_create_avance_salaire_integrations_tables.php',
            '2026_04_29_120000_add_code_operation_to_avance_salaire_baremes_table.php',
            '2026_05_07_231700_add_signatures_to_avance_salaire_demandes_table.php',
        ];

        foreach ($files as $file) {
            $migration = require database_path('migrations/'.$file);
            $migration->up();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('avance_salaire_integration_lignes');
        Schema::dropIfExists('avance_salaire_integrations');
        Schema::dropIfExists('avance_salaire_demandes');
        Schema::dropIfExists('avance_salaire_baremes');
    }
};
