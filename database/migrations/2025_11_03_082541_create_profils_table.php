<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('matricule')->unique();
            $table->string('prenom');
            $table->string('nom');
            $table->string('fonction')->nullable();
            $table->string('departement')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('telephone', 20)->nullable();
            $table->string('site')->nullable();
            $table->enum('type_contrat', ['CDI','CDD','Stagiaire','Autre'])->default('CDI');
            $table->enum('statut', ['actif','inactif'])->default('actif');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profils');
    }
};
