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
        Schema::create('habilitations', function (Blueprint $table) {
            $table->id();
            
            // Informations du demandeur
            $table->foreignId('demandeur_profile_id')->constrained('profiles')->onDelete('cascade');
            $table->string('demandeur_direction')->nullable();
            $table->string('demandeur_email')->nullable();
            $table->string('demandeur_telephone')->nullable();
            
            // Informations du bénéficiaire
            $table->foreignId('beneficiaire_profile_id')->constrained('profiles')->onDelete('cascade');
            $table->string('beneficiaire_direction')->nullable();
            $table->string('beneficiaire_email')->nullable();
            $table->string('beneficiaire_telephone')->nullable();
            $table->string('beneficiaire_site')->nullable();
            
            // Détails de la demande
            $table->enum('type_demande', ['Creation', 'Modification', 'Desactivation', 'Suppression'])->default('Creation');
            $table->json('applications')->nullable(); // Liste des applications/services demandés
            $table->string('autre_application')->nullable(); // Si "Autres" est sélectionné
            $table->text('profil_actuel')->nullable();
            $table->text('profil_demande')->nullable();
            $table->date('date_implementation_souhaitee')->nullable();
            $table->enum('type_profil', ['Consultation simple', 'Profil Specifique'])->nullable();
            $table->text('profil_specifique')->nullable();
            $table->enum('periode_validite', ['Permanent', 'Temporaire'])->default('Permanent');
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->text('motif_demande')->nullable();
            
            // Filiale
            $table->string('filiale')->nullable();
            
            // Workflow - Statut de la demande
            $table->enum('statut', [
                'brouillon',
                'en_attente_n1',
                'en_attente_controle',
                'en_attente_n2',
                'approuvee',
                'rejetee',
                'en_cours_execution',
                'terminee'
            ])->default('brouillon');
            
            // Validations
            $table->foreignId('validateur_n1_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validation_n1_at')->nullable();
            $table->text('commentaire_n1')->nullable();
            
            $table->foreignId('validateur_controle_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validation_controle_at')->nullable();
            $table->text('commentaire_controle')->nullable();
            
            $table->foreignId('validateur_n2_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validation_n2_at')->nullable();
            $table->text('commentaire_n2')->nullable();
            
            // Exécution IT
            $table->foreignId('executeur_it_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('execution_it_at')->nullable();
            $table->text('commentaire_it')->nullable();
            $table->boolean('notifie_demandeur')->default(false);
            $table->boolean('notifie_n1')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('habilitations');
    }
};
