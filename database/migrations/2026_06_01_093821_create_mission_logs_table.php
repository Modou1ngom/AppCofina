<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // L'auteur de l'action
            
            $table->string('action'); // soumission, approbation, renvoi, rejet
            $table->string('etape_concernee'); // N+2, RH, Facilities, Finance
            $table->text('commentaire')->nullable(); // Le motif obligatoire en cas de renvoi/rejet
            $table->string('signature_hash')->nullable(); // Pour la signature électronique
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_logs');
    }
};
