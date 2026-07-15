<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'facilities_retour_finance')) {
                $table->boolean('facilities_retour_finance')->default(false)->after('finance_logistique_validee_par');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('missions') || ! Schema::hasColumn('missions', 'facilities_retour_finance')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn('facilities_retour_finance');
        });
    }
};
