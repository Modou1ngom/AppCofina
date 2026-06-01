<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquete_satisfaction_reponses', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 120)->nullable();
            $table->string('matricule', 64)->nullable();
            $table->string('service', 120)->nullable();
            $table->unsignedTinyInteger('qualite_accueil_ecoute');
            $table->unsignedTinyInteger('rapidite_prise_en_charge');
            $table->unsignedTinyInteger('temps_resolution');
            $table->unsignedTinyInteger('professionnalisme_equipe_it');
            $table->unsignedTinyInteger('qualite_solution');
            $table->unsignedTinyInteger('communication_suivi');
            $table->unsignedTinyInteger('satisfaction_globale');
            $table->text('remarques_difficultes')->nullable();
            $table->text('suggestions_amelioration')->nullable();
            $table->text('besoins_attentes')->nullable();
            $table->string('recommandation', 16);
            $table->string('qualite_prise_en_charge', 32);
            $table->string('delai_reponse', 16);
            $table->text('commentaires_additionnels')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquete_satisfaction_reponses');
    }
};
