<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('missions')
            ->where('current_step', 'ATTENTE_RH_LOGISTIQUE')
            ->update(['current_step' => 'ATTENTE_RH']);
    }

    public function down(): void
    {
        DB::table('missions')
            ->where('current_step', 'ATTENTE_RH')
            ->update(['current_step' => 'ATTENTE_RH_LOGISTIQUE']);
    }
};
