<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->text('description')->nullable()->after('objet');
            $table->boolean('besoin_vehicule')->default(false)->after('budget');
            $table->boolean('besoin_chauffeur')->default(false)->after('besoin_vehicule');
            $table->boolean('besoin_hebergement')->default(false)->after('besoin_chauffeur');
            $table->boolean('besoin_transport')->default(false)->after('besoin_hebergement');
            $table->text('commentaire_rh')->nullable()->after('besoin_transport');
            $table->string('vehicule_attribue')->nullable()->after('commentaire_rh');
            $table->foreignId('chauffeur_id')->nullable()->after('vehicule_attribue')->constrained('users')->nullOnDelete();
            $table->string('logement_attribue')->nullable()->after('chauffeur_id');
            $table->decimal('prix_carburant_estime', 15, 2)->nullable()->after('logement_attribue');
            $table->decimal('prix_transport_estime', 15, 2)->nullable()->after('prix_carburant_estime');
            $table->decimal('prix_logement_estime', 15, 2)->nullable()->after('prix_transport_estime');
            $table->decimal('autres_frais_logistique', 15, 2)->nullable()->after('prix_logement_estime');
            $table->text('commentaire_facilities')->nullable()->after('autres_frais_logistique');
            $table->string('pdf_path')->nullable()->after('commentaire_facilities');
        });

        Schema::table('mission_user', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_user', 'mission_id')) {
                $table->foreignId('mission_id')->after('id')->constrained('missions')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('mission_user', 'user_id')) {
                $table->foreignId('user_id')->after('mission_id')->constrained('users')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('mission_user', 'role_dans_mission')) {
                $table->string('role_dans_mission')->default('missionnaire')->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mission_user', function (Blueprint $table) {
            if (Schema::hasColumn('mission_user', 'role_dans_mission')) {
                $table->dropColumn('role_dans_mission');
            }
            if (Schema::hasColumn('mission_user', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('mission_user', 'mission_id')) {
                $table->dropForeign(['mission_id']);
                $table->dropColumn('mission_id');
            }
        });

        Schema::table('missions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chauffeur_id');
            $table->dropColumn([
                'description',
                'besoin_vehicule',
                'besoin_chauffeur',
                'besoin_hebergement',
                'besoin_transport',
                'commentaire_rh',
                'vehicule_attribue',
                'logement_attribue',
                'prix_carburant_estime',
                'prix_transport_estime',
                'prix_logement_estime',
                'autres_frais_logistique',
                'commentaire_facilities',
                'pdf_path',
            ]);
        });
    }
};
