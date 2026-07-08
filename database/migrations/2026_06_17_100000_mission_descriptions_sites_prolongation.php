<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->json('descriptions_sites')->nullable()->after('sites_mission');
            $table->json('prolongation_donnees')->nullable()->after('duree_modifiee_at');
            $table->string('etape_reprise_apres_prolongation', 64)->nullable()->after('prolongation_donnees');
            $table->string('ordre_prolongation_pdf_path')->nullable()->after('pdf_path');
            $table->timestamp('ordre_prolongation_signe_at')->nullable()->after('ordre_prolongation_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn([
                'descriptions_sites',
                'prolongation_donnees',
                'etape_reprise_apres_prolongation',
                'ordre_prolongation_pdf_path',
                'ordre_prolongation_signe_at',
            ]);
        });
    }
};
