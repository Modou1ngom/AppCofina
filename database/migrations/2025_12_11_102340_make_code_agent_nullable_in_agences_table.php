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
        Schema::table('agences', function (Blueprint $table) {
            // Supprimer la contrainte unique sur code_agent
            $table->dropUnique(['code_agent']);
            // Rendre le champ nullable
            $table->string('code_agent')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            // Remettre la contrainte unique et non nullable
            $table->string('code_agent')->nullable(false)->change();
            $table->unique('code_agent');
        });
    }
};
