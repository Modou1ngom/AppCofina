<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stocke déjà le texte sans limite utile ; MODIFY est réservé à MySQL/MariaDB.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('mission_logs', 'signature_image')) {
            DB::statement('ALTER TABLE mission_logs MODIFY signature_image LONGTEXT NULL');
        }

        if (Schema::hasColumn('missions', 'rapport_signature_image')) {
            DB::statement('ALTER TABLE missions MODIFY rapport_signature_image LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('mission_logs', 'signature_image')) {
            DB::statement('ALTER TABLE mission_logs MODIFY signature_image TEXT NULL');
        }

        if (Schema::hasColumn('missions', 'rapport_signature_image')) {
            DB::statement('ALTER TABLE missions MODIFY rapport_signature_image TEXT NULL');
        }
    }
};
