<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            if (Schema::hasColumn('agences', 'localisation')) {
                $table->dropColumn('localisation');
            }
        });

        Schema::table('agences', function (Blueprint $table) {
            if (! Schema::hasColumn('agences', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('description');
                $table->decimal('longitude', 11, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            if (Schema::hasColumn('agences', 'latitude')) {
                $table->dropColumn(['latitude', 'longitude']);
            }
        });

        Schema::table('agences', function (Blueprint $table) {
            if (! Schema::hasColumn('agences', 'localisation')) {
                $table->text('localisation')->nullable()->after('description');
            }
        });
    }
};
