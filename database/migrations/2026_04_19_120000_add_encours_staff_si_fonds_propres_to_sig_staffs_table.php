<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sig_staffs', function (Blueprint $table) {
            $table->decimal('encours_staff_si', 15, 2)->default(0)->after('kyc_telephone')
                ->comment('Encours crédit propre (SI) du déclarant');
            $table->decimal('fonds_propres', 18, 2)->nullable()->after('encours_credit_individuel')
                ->comment('Fonds propres de référence pour le taux d’encours');
        });
    }

    public function down(): void
    {
        Schema::table('sig_staffs', function (Blueprint $table) {
            $table->dropColumn(['encours_staff_si', 'fonds_propres']);
        });
    }
};
