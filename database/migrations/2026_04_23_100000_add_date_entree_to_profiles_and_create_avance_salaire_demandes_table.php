<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('profiles', 'date_entree')) {
                $table->date('date_entree')->nullable()->after('type_contrat');
            }
        });

        Schema::create('avance_salaire_demandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('matricule', 64);
            $table->string('nom', 128);
            $table->string('prenom', 128);
            $table->decimal('montant', 15, 2);
            $table->unsignedTinyInteger('duree_mois');
            $table->date('date_premiere_echeance');
            $table->decimal('salaire_net', 15, 2);
            $table->boolean('salaire_domicilie')->default(false);
            $table->decimal('taux_interet_annuel_pct', 5, 2)->default(0);
            $table->decimal('plafond_pct_applique', 5, 2);
            $table->decimal('montant_max_autorise', 15, 2);
            $table->boolean('eligible')->default(false);
            $table->json('eligibilite_messages')->nullable();
            $table->decimal('mensualite', 15, 2)->nullable();
            $table->date('date_fin_prevue')->nullable();
            $table->json('tableau_amortissement')->nullable();
            $table->string('statut', 32)->default('brouillon');
            $table->string('statut_avant_attente', 32)->nullable();

            $table->timestamp('rh_decided_at')->nullable();
            $table->foreignId('rh_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rh_commentaire')->nullable();

            $table->timestamp('finance_decided_at')->nullable();
            $table->foreignId('finance_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('finance_commentaire')->nullable();

            $table->foreignId('filiale_id')->nullable()->constrained('filiales')->nullOnDelete();
            $table->timestamps();

            $table->index(['statut', 'created_at']);
            $table->index('profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avance_salaire_demandes');

        Schema::table('profiles', function (Blueprint $table) {
            if (Schema::hasColumn('profiles', 'date_entree')) {
                $table->dropColumn('date_entree');
            }
        });
    }
};
