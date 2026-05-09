<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            if (! Schema::hasColumn('avance_salaire_demandes', 'dates_tranches')) {
                $table->json('dates_tranches')->nullable()->after('mode_paiement');
            }
        });

        if (Schema::hasColumn('avance_salaire_demandes', 'intervalle_tranche_mois')) {
            Schema::table('avance_salaire_demandes', function (Blueprint $table) {
                $table->dropColumn('intervalle_tranche_mois');
            });
        }
    }

    public function down(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            if (Schema::hasColumn('avance_salaire_demandes', 'dates_tranches')) {
                $table->dropColumn('dates_tranches');
            }
        });

        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            if (! Schema::hasColumn('avance_salaire_demandes', 'intervalle_tranche_mois')) {
                $table->unsignedTinyInteger('intervalle_tranche_mois')->nullable()->after('mode_paiement');
            }
        });
    }
};
