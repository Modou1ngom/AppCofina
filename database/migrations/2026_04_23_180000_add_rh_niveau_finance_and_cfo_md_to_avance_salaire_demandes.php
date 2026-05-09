<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('avance_salaire_demandes', 'rh_niveau_finance')) {
            return;
        }

        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            $table->string('rh_niveau_finance', 16)->nullable()->after('rh_commentaire');
            $table->timestamp('cfo_validated_at')->nullable()->after('rh_niveau_finance');
            $table->foreignId('cfo_validated_by')->nullable()->after('cfo_validated_at')->constrained('users')->nullOnDelete();
            $table->text('cfo_commentaire')->nullable()->after('cfo_validated_by');
            $table->timestamp('md_validated_at')->nullable()->after('cfo_commentaire');
            $table->foreignId('md_validated_by')->nullable()->after('md_validated_at')->constrained('users')->nullOnDelete();
            $table->text('md_commentaire')->nullable()->after('md_validated_by');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('avance_salaire_demandes', 'rh_niveau_finance')) {
            return;
        }

        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            $table->dropForeign(['cfo_validated_by']);
            $table->dropForeign(['md_validated_by']);
            $table->dropColumn([
                'rh_niveau_finance',
                'cfo_validated_at',
                'cfo_validated_by',
                'cfo_commentaire',
                'md_validated_at',
                'md_validated_by',
                'md_commentaire',
            ]);
        });
    }
};
