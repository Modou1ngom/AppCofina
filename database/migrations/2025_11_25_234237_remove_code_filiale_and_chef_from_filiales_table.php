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
        // SQLite ne supporte pas dropColumn directement, on doit recréer la table
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('filiales', function (Blueprint $table) {
                // Pour SQLite, on doit recréer la table sans ces colonnes
            });
            
            // Recréer la table sans les colonnes à supprimer
            Schema::dropIfExists('filiales');
            Schema::create('filiales', function (Blueprint $table) {
                $table->id();
                $table->string('nom')->unique();
                $table->text('description')->nullable();
                $table->boolean('actif')->default(true);
                $table->timestamps();
            });
        } else {
            Schema::table('filiales', function (Blueprint $table) {
                if (Schema::hasColumn('filiales', 'code_filiale')) {
                    $table->dropColumn('code_filiale');
                }
                if (Schema::hasColumn('filiales', 'chef_filiale_id')) {
                    $table->dropForeign(['chef_filiale_id']);
                    $table->dropColumn('chef_filiale_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('filiales', function (Blueprint $table) {
            $table->string('code_filiale')->unique()->after('nom');
            $table->foreignId('chef_filiale_id')->nullable()->after('actif')->constrained('profiles')->onDelete('set null');
        });
    }
};
