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
        Schema::table('habilitations', function (Blueprint $table) {
            $table->string('messagerie_email')->nullable()->after('subsidiary');
            $table->string('messagerie_nom_affichage')->nullable()->after('messagerie_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habilitations', function (Blueprint $table) {
            $table->dropColumn([
                'messagerie_email',
                'messagerie_nom_affichage',
            ]);
        });
    }
};
