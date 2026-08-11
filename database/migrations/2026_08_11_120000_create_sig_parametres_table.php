<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sig_parametres', function (Blueprint $table) {
            $table->id();
            $table->decimal('fonds_propres', 18, 2)->nullable()
                ->comment('Fonds propres de référence (banque) pour le calcul des ratios');
            $table->decimal('seuil_taux_pct', 8, 2)->default(10)
                ->comment('Seuil de dépassement (%) — ratio > seuil → Dépassement');
            $table->decimal('alerte_taux_pct', 8, 2)->default(8)
                ->comment('Début zone Alerte (%) — alerte ≤ ratio ≤ seuil');
            $table->timestamps();
        });

        DB::table('sig_parametres')->insert([
            'fonds_propres' => null,
            'seuil_taux_pct' => (float) config('sig.encours_taux_seuil_pct', 10),
            'alerte_taux_pct' => (float) config('sig.encours_taux_alerte_pct', 8),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sig_parametres');
    }
};
