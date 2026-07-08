<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_rapport_pieces_jointes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nom_fichier');
            $table->string('chemin');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('taille');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_rapport_pieces_jointes');
    }
};
