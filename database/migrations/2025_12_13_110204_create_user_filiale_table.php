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
        Schema::create('user_filiale', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('filiale_id')->constrained('filiales')->onDelete('cascade');
            $table->timestamps();
            
            // S'assurer qu'un utilisateur ne peut avoir qu'une seule fois la même filiale
            $table->unique(['user_id', 'filiale_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_filiale');
    }
};
