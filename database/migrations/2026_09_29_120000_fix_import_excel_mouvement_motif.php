<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('profil_mouvements')
            ->where('motif', 'Import Excel')
            ->update(['motif' => 'Enrôlement staff']);
    }

    public function down(): void
    {
        // Les arrivées créées à la main portent déjà « Enrôlement staff ».
        // On ne les réécrit pas en « Import Excel ».
    }
};
