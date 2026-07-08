<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mission_user', function (Blueprint $table) {
            $table->json('logistique_sites')->nullable()->after('autres_frais');
        });

        Schema::table('missions', function (Blueprint $table) {
            $table->json('sites_prolongation')->nullable()->after('descriptions_sites');
            $table->json('descriptions_sites_prolongation')->nullable()->after('sites_prolongation');
        });
    }

    public function down(): void
    {
        Schema::table('mission_user', function (Blueprint $table) {
            $table->dropColumn('logistique_sites');
        });

        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn(['sites_prolongation', 'descriptions_sites_prolongation']);
        });
    }
};
