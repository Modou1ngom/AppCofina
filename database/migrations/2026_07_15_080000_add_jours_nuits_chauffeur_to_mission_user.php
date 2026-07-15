<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_user')) {
            return;
        }

        Schema::table('mission_user', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_user', 'jours')) {
                $table->unsignedInteger('jours')->nullable()->after('autres_frais');
            }
            if (! Schema::hasColumn('mission_user', 'nuits')) {
                $table->unsignedInteger('nuits')->nullable()->after('jours');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_user')) {
            return;
        }

        Schema::table('mission_user', function (Blueprint $table) {
            if (Schema::hasColumn('mission_user', 'nuits')) {
                $table->dropColumn('nuits');
            }
            if (Schema::hasColumn('mission_user', 'jours')) {
                $table->dropColumn('jours');
            }
        });
    }
};
