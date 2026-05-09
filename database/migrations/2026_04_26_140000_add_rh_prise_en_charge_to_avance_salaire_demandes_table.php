<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            $table->timestamp('rh_prise_en_charge_at')->nullable()->after('finance_commentaire');
            $table->foreignId('rh_prise_en_charge_by')->nullable()->after('rh_prise_en_charge_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            $table->dropForeign(['rh_prise_en_charge_by']);
            $table->dropColumn(['rh_prise_en_charge_at', 'rh_prise_en_charge_by']);
        });
    }
};
