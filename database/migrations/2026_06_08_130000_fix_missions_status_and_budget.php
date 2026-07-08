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

        DB::statement("UPDATE missions SET status = 'valide' WHERE status = 'validee'");
        DB::statement("UPDATE missions SET status = 'rejete' WHERE status = 'rejetee'");
        DB::statement("UPDATE missions SET status = 'en_cours' WHERE status = 'completee'");

        DB::statement("ALTER TABLE missions MODIFY status VARCHAR(20) NOT NULL DEFAULT 'en_cours'");
        DB::statement('ALTER TABLE missions MODIFY budget DECIMAL(15,2) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        DB::statement("UPDATE missions SET status = 'validee' WHERE status = 'valide'");
        DB::statement("UPDATE missions SET status = 'rejetee' WHERE status = 'rejete'");

        DB::statement("ALTER TABLE missions MODIFY status ENUM('en_cours','validee','rejetee','completee') NOT NULL DEFAULT 'en_cours'");
        DB::statement('ALTER TABLE missions MODIFY budget DECIMAL(15,2) NOT NULL');
    }
};
