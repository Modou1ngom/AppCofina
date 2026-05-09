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
        Schema::create('sig_personnes_liees', function (Blueprint $table) {
            $table->id();
            $table->boolean('est_personne_morale')->default(false);
            $table->string('prenom')->nullable();
            $table->string('nom')->nullable();
            $table->string('raison_sociale')->nullable();
            $table->string('kyc_piece_identite')->nullable()->comment('CNI / Passeport / RCCM');
            $table->text('kyc_adresse')->nullable();
            $table->string('kyc_telephone')->nullable();
            $table->decimal('encours_credit', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sig_personnes_liees');
    }
};
