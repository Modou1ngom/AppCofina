<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mission_user', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_user', 'chauffeur_profil_id')) {
                $table->foreignId('chauffeur_profil_id')
                    ->nullable()
                    ->after('chauffeur_id')
                    ->constrained('profiles')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mission_user', function (Blueprint $table) {
            if (Schema::hasColumn('mission_user', 'chauffeur_profil_id')) {
                $table->dropForeign(['chauffeur_profil_id']);
                $table->dropColumn('chauffeur_profil_id');
            }
        });
    }
};
