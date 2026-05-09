<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avance_salaire_baremes', function (Blueprint $table) {
            $table->string('code_operation', 32)->nullable()->after('compte_charge');
        });
    }

    public function down(): void
    {
        Schema::table('avance_salaire_baremes', function (Blueprint $table) {
            $table->dropColumn('code_operation');
        });
    }
};
