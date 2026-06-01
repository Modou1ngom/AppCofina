<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pointage_sites', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code_public', 64)->unique();
            $table->string('secret_token', 64)->unique();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('pointages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pointage_site_id')->constrained()->cascadeOnDelete();
            $table->string('sens', 16);
            $table->timestamp('enregistre_at');
            $table->string('source', 32)->default('scan');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'enregistre_at']);
            $table->index(['pointage_site_id', 'enregistre_at']);
        });

        Schema::create('pointage_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date_concernee');
            $table->text('motif');
            $table->string('statut', 32)->default('pending_manager');
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at_manager')->nullable();
            $table->text('commentaire_manager')->nullable();
            $table->foreignId('rh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at_rh')->nullable();
            $table->text('commentaire_rh')->nullable();
            $table->timestamps();

            $table->index(['statut', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pointage_declarations');
        Schema::dropIfExists('pointages');
        Schema::dropIfExists('pointage_sites');
    }
};
