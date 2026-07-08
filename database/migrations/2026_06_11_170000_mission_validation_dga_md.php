<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mission_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_logs', 'signature_image')) {
                $table->text('signature_image')->nullable()->after('signature_hash');
            }
        });

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'md_signe_at')) {
                $table->timestamp('md_signe_at')->nullable()->after('commentaire_facilities');
            }
            if (! Schema::hasColumn('missions', 'dga_contournee')) {
                $table->boolean('dga_contournee')->default(false)->after('md_signe_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mission_logs', function (Blueprint $table) {
            if (Schema::hasColumn('mission_logs', 'signature_image')) {
                $table->dropColumn('signature_image');
            }
        });

        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn(['md_signe_at', 'dga_contournee']);
        });
    }
};
