<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sig_staffs', function (Blueprint $table) {
            $table->boolean('encours_conformite_en_depassement')->default(false)->after('fonds_propres');
        });
    }

    public function down(): void
    {
        Schema::table('sig_staffs', function (Blueprint $table) {
            $table->dropColumn('encours_conformite_en_depassement');
        });
    }
};
