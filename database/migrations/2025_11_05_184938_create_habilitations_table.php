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
            $table->foreignId('requester_profile_id')->constrained('profiles')->onDelete('cascade');
            $table->string('requester_direction')->nullable();
            $table->string('requester_email')->nullable();
            $table->string('requester_telephone')->nullable();
            
            // Informations du bénéficiaire
            $table->foreignId('beneficiary_profile_id')->constrained('profiles')->onDelete('cascade');
            $table->string('beneficiary_direction')->nullable();
            $table->string('beneficiary_email')->nullable();
            $table->string('beneficiary_telephone')->nullable();
            $table->string('beneficiary_site')->nullable();
            
            // Détails de la demande
            $table->enum('request_type', ['Creation', 'Modification', 'Desactivation', 'Suppression'])->default('Creation');
            $table->json('applications')->nullable(); // Liste des applications/services demandés
            $table->string('other_application')->nullable(); // Si "Autres" est sélectionné
            $table->text('current_profile')->nullable();
            $table->text('requested_profile')->nullable();
            $table->date('desired_implementation_date')->nullable();
            $table->enum('profile_type', ['Consultation simple', 'Profil Specifique'])->nullable();
            $table->text('specific_profile')->nullable();
            $table->enum('validity_period', ['Permanent', 'Temporaire'])->default('Permanent');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('request_reason')->nullable();
            
            // Filiale
            $table->string('subsidiary')->nullable();
            
            // Workflow - Statut de la demande
            $table->enum('status', [
                'draft',
                'pending_n1',
                'pending_control',
                'pending_n2',
                'approved',
                'rejected',
                'in_progress',
                'completed'
            ])->default('draft');
            
            // Validations
            $table->foreignId('validator_n1_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validated_n1_at')->nullable();
            $table->text('comment_n1')->nullable();
            
            $table->foreignId('validator_control_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validated_control_at')->nullable();
            $table->text('comment_control')->nullable();
            
            $table->foreignId('validator_n2_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validated_n2_at')->nullable();
            $table->text('comment_n2')->nullable();
            
            // Exécution IT
            $table->foreignId('executor_it_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('executed_it_at')->nullable();
            $table->text('comment_it')->nullable();
            $table->boolean('notify_requester')->default(false);
            $table->boolean('notify_n1')->default(false);
            
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
