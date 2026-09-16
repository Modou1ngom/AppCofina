<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('missions') || ! Schema::hasColumn('missions', 'budget')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            $table->decimal('budget', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('missions') || ! Schema::hasColumn('missions', 'budget')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            $table->decimal('budget', 15, 2)->nullable(false)->change();
        });
    }
};
