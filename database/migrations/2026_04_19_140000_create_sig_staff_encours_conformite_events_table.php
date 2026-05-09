<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sig_staff_encours_conformite_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sig_staff_id')->constrained('sig_staffs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->decimal('fonds_propres', 18, 2)->nullable();
            $table->decimal('encours_consolide', 18, 2);
            $table->decimal('taux_pct', 10, 2)->nullable();
            $table->decimal('seuil_pct', 10, 2);
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->index(['sig_staff_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sig_staff_encours_conformite_events');
    }
};
