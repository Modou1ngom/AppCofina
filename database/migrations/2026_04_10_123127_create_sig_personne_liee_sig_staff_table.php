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
        Schema::create('sig_personne_liee_sig_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sig_staff_id')->constrained('sig_staffs')->cascadeOnDelete();
            $table->foreignId('sig_personne_liee_id')->constrained('sig_personnes_liees')->cascadeOnDelete();
            $table->string('type_relation');
            $table->unsignedTinyInteger('classe')->comment('Classe réglementaire 1 à 4');
            $table->timestamps();

            $table->unique(['sig_staff_id', 'sig_personne_liee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sig_personne_liee_sig_staff');
    }
};
