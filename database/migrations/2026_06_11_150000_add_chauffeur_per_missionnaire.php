<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mission_user', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_user', 'besoin_chauffeur')) {
                $table->boolean('besoin_chauffeur')->default(false)->after('autres_frais');
            }
            if (! Schema::hasColumn('mission_user', 'chauffeur_id')) {
                $table->foreignId('chauffeur_id')->nullable()->after('besoin_chauffeur')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mission_user', function (Blueprint $table) {
            if (Schema::hasColumn('mission_user', 'chauffeur_id')) {
                $table->dropConstrainedForeignId('chauffeur_id');
            }
            if (Schema::hasColumn('mission_user', 'besoin_chauffeur')) {
                $table->dropColumn('besoin_chauffeur');
            }
        });
    }
};
