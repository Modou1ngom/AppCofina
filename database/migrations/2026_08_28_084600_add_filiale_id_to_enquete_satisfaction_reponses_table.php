<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquete_satisfaction_reponses', function (Blueprint $table) {
            $table->foreignId('filiale_id')
                ->nullable()
                ->after('id')
                ->constrained('filiales')
                ->nullOnDelete();
        });

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

        if ($senegalId) {
            DB::table('enquete_satisfaction_reponses')
                ->whereNull('filiale_id')
                ->update(['filiale_id' => $senegalId]);
        }
    }

    public function down(): void
    {
        Schema::table('enquete_satisfaction_reponses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('filiale_id');
        });
    }
};
