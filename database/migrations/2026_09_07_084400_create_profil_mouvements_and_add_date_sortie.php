<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('profiles', 'date_sortie')) {
                $after = Schema::hasColumn('profiles', 'date_entree') ? 'date_entree' : 'type_contrat';
                $table->date('date_sortie')->nullable()->after($after);
            }
            if (! Schema::hasColumn('profiles', 'motif_depart')) {
                $table->string('motif_depart')->nullable()->after('date_sortie');
            }
        });

        Schema::create('profil_mouvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profil_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('type', 32);
            $table->date('date_effet');
            $table->string('fonction_avant')->nullable();
            $table->string('fonction_apres')->nullable();
            $table->string('departement_avant')->nullable();
            $table->string('departement_apres')->nullable();
            $table->string('site_avant')->nullable();
            $table->string('site_apres')->nullable();
            $table->foreignId('n_plus_1_id_avant')->nullable()->constrained('profiles')->nullOnDelete();
            $table->foreignId('n_plus_1_id_apres')->nullable()->constrained('profiles')->nullOnDelete();
            $table->text('motif')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['profil_id', 'type']);
            $table->index('date_effet');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profil_mouvements');

        Schema::table('profiles', function (Blueprint $table) {
            if (Schema::hasColumn('profiles', 'motif_depart')) {
                $table->dropColumn('motif_depart');
            }
            if (Schema::hasColumn('profiles', 'date_sortie')) {
                $table->dropColumn('date_sortie');
            }
        });
    }
};
