<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            if (! Schema::hasColumn('avance_salaire_demandes', 'mode_paiement')) {
                $table->string('mode_paiement', 16)->default('par_mois')->after('type_avance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            if (Schema::hasColumn('avance_salaire_demandes', 'mode_paiement')) {
                $table->dropColumn('mode_paiement');
            }
        });
    }
};
