<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sig_staffs', function (Blueprint $table) {
            $table->string('numero_client_si', 100)->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('sig_staffs', function (Blueprint $table) {
            $table->dropColumn('numero_client_si');
        });
    }
};
