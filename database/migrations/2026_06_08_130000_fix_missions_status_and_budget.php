<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        DB::table('missions')->where('status', 'validee')->update(['status' => 'valide']);
        DB::table('missions')->where('status', 'rejetee')->update(['status' => 'rejete']);
        DB::table('missions')->where('status', 'completee')->update(['status' => 'en_cours']);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite n'accepte pas MODIFY : les types restent souples (TEXT / NUMERIC).
            return;
        }

        DB::statement("ALTER TABLE missions MODIFY status VARCHAR(20) NOT NULL DEFAULT 'en_cours'");
        DB::statement('ALTER TABLE missions MODIFY budget DECIMAL(15,2) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        DB::table('missions')->where('status', 'valide')->update(['status' => 'validee']);
        DB::table('missions')->where('status', 'rejete')->update(['status' => 'rejetee']);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE missions MODIFY status ENUM('en_cours','validee','rejetee','completee') NOT NULL DEFAULT 'en_cours'");
        DB::statement('ALTER TABLE missions MODIFY budget DECIMAL(15,2) NOT NULL');
    }
};
