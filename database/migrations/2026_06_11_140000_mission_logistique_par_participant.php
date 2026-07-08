<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mission_user', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_user', 'vehicule')) {
                $table->string('vehicule')->nullable()->after('role_dans_mission');
            }
            if (! Schema::hasColumn('mission_user', 'logement')) {
                $table->string('logement')->nullable()->after('vehicule');
            }
            if (! Schema::hasColumn('mission_user', 'per_diem')) {
                $table->decimal('per_diem', 15, 2)->nullable()->after('logement');
            }
            if (! Schema::hasColumn('mission_user', 'prix_carburant')) {
                $table->decimal('prix_carburant', 15, 2)->default(0)->after('per_diem');
            }
            if (! Schema::hasColumn('mission_user', 'prix_transport')) {
                $table->decimal('prix_transport', 15, 2)->default(0)->after('prix_carburant');
            }
            if (! Schema::hasColumn('mission_user', 'prix_logement')) {
                $table->decimal('prix_logement', 15, 2)->default(0)->after('prix_transport');
            }
            if (! Schema::hasColumn('mission_user', 'autres_frais')) {
                $table->decimal('autres_frais', 15, 2)->default(0)->after('prix_logement');
            }
        });

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'total_logistique')) {
                $table->decimal('total_logistique', 15, 2)->nullable()->after('autres_frais_logistique');
            }
        });

        DB::table('missions')
            ->where('current_step', 'ATTENTE_RH')
            ->update(['current_step' => 'ATTENTE_FACILITIES']);
    }

    public function down(): void
    {
        Schema::table('mission_user', function (Blueprint $table) {
            $table->dropColumn([
                'vehicule',
                'logement',
                'per_diem',
                'prix_carburant',
                'prix_transport',
                'prix_logement',
                'autres_frais',
            ]);
        });

        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn('total_logistique');
        });
    }
};
