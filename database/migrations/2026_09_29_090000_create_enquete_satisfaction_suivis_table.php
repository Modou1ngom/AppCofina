<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquete_satisfaction_suivis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reponse_id');
            $table->string('type', 24);
            $table->string('decision', 24)->nullable();
            $table->string('statut', 32)->default('a_analyser');
            $table->text('element_reponse')->nullable();
            $table->text('action')->nullable();
            $table->text('justification')->nullable();
            $table->date('date_cloture')->nullable();
            $table->timestamps();

            $table->unique(['reponse_id', 'type'], 'enq_suivi_reponse_type_uq');
            $table->foreign('reponse_id', 'enq_suivi_reponse_fk')
                ->references('id')
                ->on('enquete_satisfaction_reponses')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquete_satisfaction_suivis');
    }
};
