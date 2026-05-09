<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            $table->text('signature_employe')->nullable()->after('finance_commentaire');
            $table->foreignId('signature_employe_by')->nullable()->after('signature_employe')->constrained('users')->nullOnDelete();
            $table->timestamp('signature_employe_at')->nullable()->after('signature_employe_by');
            $table->text('signature_rh')->nullable()->after('signature_employe_at');
            $table->foreignId('signature_rh_by')->nullable()->after('signature_rh')->constrained('users')->nullOnDelete();
            $table->timestamp('signature_rh_at')->nullable()->after('signature_rh_by');
            $table->text('signature_finance')->nullable()->after('signature_rh_at');
            $table->foreignId('signature_finance_by')->nullable()->after('signature_finance')->constrained('users')->nullOnDelete();
            $table->timestamp('signature_finance_at')->nullable()->after('signature_finance_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('avance_salaire_demandes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signature_employe_by');
            $table->dropConstrainedForeignId('signature_rh_by');
            $table->dropConstrainedForeignId('signature_finance_by');
            $table->dropColumn([
                'signature_employe',
                'signature_employe_at',
                'signature_rh',
                'signature_rh_at',
                'signature_finance',
                'signature_finance_at',
            ]);
        });
    }
};
