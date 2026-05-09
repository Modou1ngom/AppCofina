<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            $table->timestamp('rh_traitement_termine_at')->nullable()->after('rh_prise_en_charge_by');
            $table->foreignId('rh_traitement_termine_by')->nullable()->after('rh_traitement_termine_at')->constrained('users')->nullOnDelete();
        });

        DB::table('avance_salaire_demandes')
            ->where('statut', 'approuvee')
            ->whereNull('rh_prise_en_charge_at')
            ->update(['statut' => 'en_attente_prise_en_charge']);

        DB::table('avance_salaire_demandes')
            ->where('statut', 'approuvee')
            ->whereNotNull('rh_prise_en_charge_at')
            ->update(['statut' => 'en_cours_traitement']);
    }

    public function down(): void
    {
        DB::table('avance_salaire_demandes')
            ->where('statut', 'en_attente_prise_en_charge')
            ->update(['statut' => 'approuvee']);

        DB::table('avance_salaire_demandes')
            ->whereIn('statut', ['en_cours_traitement', 'terminee'])
            ->update(['statut' => 'approuvee']);

        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            $table->dropForeign(['rh_traitement_termine_by']);
            $table->dropColumn(['rh_traitement_termine_at', 'rh_traitement_termine_by']);
        });
    }
};
