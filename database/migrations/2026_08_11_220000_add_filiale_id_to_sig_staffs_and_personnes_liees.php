<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $senegalId = null;
        foreach (DB::table('filiales')->orderBy('id')->get(['id', 'nom']) as $row) {
            $ascii = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $row->nom));
            if (str_contains($ascii, 'senegal')) {
                $senegalId = (int) $row->id;
                break;
            }
        }
        if (! $senegalId) {
            $senegalId = DB::table('filiales')->orderBy('id')->value('id');
        }

        Schema::table('sig_staffs', function (Blueprint $table) {
            $table->foreignId('filiale_id')
                ->nullable()
                ->after('id')
                ->constrained('filiales')
                ->nullOnDelete();
        });

        Schema::table('sig_personnes_liees', function (Blueprint $table) {
            $table->foreignId('filiale_id')
                ->nullable()
                ->after('id')
                ->constrained('filiales')
                ->nullOnDelete();
        });

        if ($senegalId) {
            DB::table('sig_staffs')->whereNull('filiale_id')->update(['filiale_id' => $senegalId]);
            DB::table('sig_personnes_liees')->whereNull('filiale_id')->update(['filiale_id' => $senegalId]);
        }

        // Unicité par environnement (même n° client possible SN vs TG)
        Schema::table('sig_staffs', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->unique(['filiale_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('sig_staffs', function (Blueprint $table) {
            $table->dropUnique(['filiale_id', 'reference']);
            $table->unique('reference');
            $table->dropConstrainedForeignId('filiale_id');
        });

        Schema::table('sig_personnes_liees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('filiale_id');
        });
    }
};
