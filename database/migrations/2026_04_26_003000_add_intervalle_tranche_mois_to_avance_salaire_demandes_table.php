<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            if (! Schema::hasColumn('avance_salaire_demandes', 'intervalle_tranche_mois')) {
                $table->unsignedTinyInteger('intervalle_tranche_mois')->nullable()->after('mode_paiement');
            }
        });
    }

    public function down(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            if (Schema::hasColumn('avance_salaire_demandes', 'intervalle_tranche_mois')) {
                $table->dropColumn('intervalle_tranche_mois');
            }
        });
    }
};
