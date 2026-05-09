<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avance_salaire_baremes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('label', 128);
            $table->string('compte_charge', 64)->nullable();
            $table->unsignedTinyInteger('duree_max_mois')->default(6);
            $table->decimal('plafond_non_cadre', 15, 2)->default(0);
            $table->decimal('plafond_cadre', 15, 2)->default(0);
            $table->decimal('plafond_emc', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $types = config('avance_salaire.types', []);
        $order = 1;
        foreach ($types as $key => $type) {
            \DB::table('avance_salaire_baremes')->insert([
                'key' => $key,
                'label' => $type['label'] ?? $key,
                'compte_charge' => $type['compte_charge'] ?? null,
                'duree_max_mois' => (int) ($type['duree_max_mois'] ?? 6),
                'plafond_non_cadre' => (float) (($type['plafonds']['non_cadre'] ?? 0)),
                'plafond_cadre' => (float) (($type['plafonds']['cadre'] ?? 0)),
                'plafond_emc' => (float) (($type['plafonds']['emc'] ?? 0)),
                'sort_order' => $order++,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('avance_salaire_baremes');
    }
};
