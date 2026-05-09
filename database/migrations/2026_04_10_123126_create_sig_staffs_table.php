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
        Schema::create('sig_staffs', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->comment('Identifiant unique réglementaire');
            $table->foreignId('profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->string('prenom');
            $table->string('nom');
            $table->string('fonction')->nullable();
            $table->string('departement')->nullable();
            $table->enum('type_personne', ['staff', 'administrateur', 'apparente_ou_liee'])->default('staff');
            $table->enum('statut', ['actif', 'inactif'])->default('actif');
            $table->string('kyc_piece_identite')->nullable()->comment('CNI / Passeport');
            $table->text('kyc_adresse')->nullable();
            $table->string('kyc_telephone')->nullable();
            $table->decimal('encours_credit_individuel', 15, 2)->default(0);
            $table->decimal('score_risque', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sig_staffs');
    }
};
