<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('profiles') || ! Schema::hasColumn('profiles', 'date_entree')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('UPDATE profiles SET date_entree = DATE(created_at) WHERE date_entree IS NULL AND created_at IS NOT NULL');

            return;
        }

        DB::statement("UPDATE profiles SET date_entree = date(created_at) WHERE date_entree IS NULL AND created_at IS NOT NULL");
    }

    public function down(): void
    {
        //
    }
};
