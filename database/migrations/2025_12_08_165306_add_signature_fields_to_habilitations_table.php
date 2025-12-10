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
        Schema::table('habilitations', function (Blueprint $table) {
            $table->text('signature_n1')->nullable()->after('comment_n1');
            $table->text('signature_n2')->nullable()->after('comment_n2');
            $table->text('signature_control')->nullable()->after('comment_control');
            $table->text('signature_it')->nullable()->after('comment_it');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habilitations', function (Blueprint $table) {
            $table->dropColumn(['signature_n1', 'signature_n2', 'signature_control', 'signature_it']);
        });
    }
};
