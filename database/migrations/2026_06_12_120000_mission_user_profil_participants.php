<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mission_user', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_user', 'profil_id')) {
                $table->foreignId('profil_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('profiles')
                    ->nullOnDelete();
            }
        });

        Schema::table('mission_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('mission_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mission_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('mission_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('mission_user', function (Blueprint $table) {
            if (Schema::hasColumn('mission_user', 'profil_id')) {
                $table->dropForeign(['profil_id']);
                $table->dropColumn('profil_id');
            }
        });
    }
};
