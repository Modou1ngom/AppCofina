<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->text('rapport_contenu')->nullable()->after('commentaire_facilities');
            $table->text('rapport_signature_image')->nullable()->after('rapport_contenu');
            $table->string('rapport_signataire_nom')->nullable()->after('rapport_signature_image');
            $table->foreignId('rapport_signataire_id')->nullable()->after('rapport_signataire_nom')->constrained('users')->nullOnDelete();
            $table->timestamp('rapport_soumis_at')->nullable()->after('rapport_signataire_id');
            $table->string('rapport_pdf_path')->nullable()->after('rapport_soumis_at');
            $table->timestamp('rapport_valide_at')->nullable()->after('rapport_pdf_path');
            $table->timestamp('duree_modifiee_at')->nullable()->after('rapport_valide_at');
            $table->timestamp('last_reminder_at')->nullable()->after('duree_modifiee_at');
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rapport_signataire_id');
            $table->dropColumn([
                'rapport_contenu',
                'rapport_signature_image',
                'rapport_signataire_nom',
                'rapport_soumis_at',
                'rapport_pdf_path',
                'rapport_valide_at',
                'duree_modifiee_at',
                'last_reminder_at',
            ]);
        });
    }
};
