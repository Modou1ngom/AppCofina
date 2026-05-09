<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            $table->string('type_avance', 32)->default('salaire')->after('prenom');
            $table->string('categorie_staff', 16)->default('non_cadre')->after('type_avance');
            $table->string('compte_staff', 64)->nullable()->after('duree_mois');
            $table->unsignedTinyInteger('nombre_avance_en_cours')->default(0)->after('compte_staff');
        });
    }

    public function down(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            $table->dropColumn(['type_avance', 'categorie_staff', 'compte_staff', 'nombre_avance_en_cours']);
        });
    }
};
