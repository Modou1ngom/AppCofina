<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('profiles') || ! Schema::hasColumn('profiles', 'type_contrat')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE profiles MODIFY type_contrat VARCHAR(20) NULL DEFAULT NULL');

            return;
        }

        // SQLite et autres : colonne nullable via change()
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('type_contrat', 20)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('profiles') || ! Schema::hasColumn('profiles', 'type_contrat')) {
            return;
        }

        DB::table('profiles')->whereNull('type_contrat')->update(['type_contrat' => 'CDI']);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE profiles MODIFY type_contrat VARCHAR(20) NOT NULL DEFAULT 'CDI'");

            return;
        }

        Schema::table('profiles', function (Blueprint $table) {
            $table->string('type_contrat', 20)->nullable(false)->default('CDI')->change();
        });
    }
};
