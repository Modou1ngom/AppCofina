<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->timestamp('finance_logistique_validee_at')->nullable()->after('total_logistique');
            $table->foreignId('finance_logistique_validee_par')
                ->nullable()
                ->after('finance_logistique_validee_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finance_logistique_validee_par');
            $table->dropColumn('finance_logistique_validee_at');
        });
    }
};
