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
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demandeur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('beneficiaire_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('n2_beneficiaire_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('objet');
            $table->text('perimetre')->nullable();
            $table->text('enjeux')->nullable();
            $table->text('risques')->nullable();
            $table->enum('priorite', ['normale', 'urgente', 'critique'])->default('normale');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('budget', 15, 2);
            $table->string('current_step')->default('ATTENTE_N2');
            $table->enum('status', ['en_cours', 'validee', 'rejetee', 'completee'])->default('en_cours');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
