<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'sites_mission')) {
                $table->json('sites_mission')->nullable()->after('perimetre');
            }
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            if (Schema::hasColumn('missions', 'sites_mission')) {
                $table->dropColumn('sites_mission');
            }
        });
    }
};
