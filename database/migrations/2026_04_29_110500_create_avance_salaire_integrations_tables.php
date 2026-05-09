<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avance_salaire_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avance_salaire_demande_id')->constrained('avance_salaire_demandes')->cascadeOnDelete();

            // Identifiant batch (format libre, utilisé dans l’export écritures)
            $table->string('no_batch', 64);

            $table->string('code_operation', 32)->nullable();
            $table->string('libelle_ecriture', 255)->nullable();

            // Date de valeur (souvent la première échéance)
            $table->date('date_de_valeur')->nullable();
            $table->unsignedSmallInteger('annee_compte')->nullable();
            $table->unsignedTinyInteger('mois_compte')->nullable();

            // Statut de l’intégration (vue / export / traitement)
            $table->string('statut', 32)->default('en_attente');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique('avance_salaire_demande_id');
        });

        Schema::create('avance_salaire_integration_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('avance_salaire_integrations')->cascadeOnDelete();

            $table->unsignedInteger('numero')->default(1);
            $table->string('no_batch', 64);

            $table->string('no_compte', 64);
            $table->string('sens', 16); // valeurs attendues : credite / debute

            $table->decimal('montant', 18, 2);
            $table->string('code_operation', 32)->nullable();
            $table->date('date_de_valeur')->nullable();

            $table->string('code_agence', 255)->nullable();
            $table->string('libelle_ecriture', 255)->nullable();

            $table->unsignedSmallInteger('annee_compte')->nullable();
            $table->unsignedTinyInteger('mois_compte')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['integration_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avance_salaire_integration_lignes');
        Schema::dropIfExists('avance_salaire_integrations');
    }
};
