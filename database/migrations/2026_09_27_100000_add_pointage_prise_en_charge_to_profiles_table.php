<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('profiles', 'pointage_statut')) {
                $table->string('pointage_statut', 32)->nullable()->after('statut');
            }
            if (! Schema::hasColumn('profiles', 'pointage_demande_at')) {
                $table->timestamp('pointage_demande_at')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'pointage_demande_par')) {
                $table->foreignId('pointage_demande_par')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('profiles', 'pointage_confirme_at')) {
                $table->timestamp('pointage_confirme_at')->nullable();
            }
            if (! Schema::hasColumn('profiles', 'pointage_confirme_par')) {
                $table->foreignId('pointage_confirme_par')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('profiles', 'pointage_commentaire')) {
                $table->text('pointage_commentaire')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (Schema::hasColumn('profiles', 'pointage_demande_par')) {
                $table->dropConstrainedForeignId('pointage_demande_par');
            }
            if (Schema::hasColumn('profiles', 'pointage_confirme_par')) {
                $table->dropConstrainedForeignId('pointage_confirme_par');
            }
            $columns = array_values(array_filter([
                Schema::hasColumn('profiles', 'pointage_statut') ? 'pointage_statut' : null,
                Schema::hasColumn('profiles', 'pointage_demande_at') ? 'pointage_demande_at' : null,
                Schema::hasColumn('profiles', 'pointage_confirme_at') ? 'pointage_confirme_at' : null,
                Schema::hasColumn('profiles', 'pointage_commentaire') ? 'pointage_commentaire' : null,
            ]));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
