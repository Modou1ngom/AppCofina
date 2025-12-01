<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Supprimer la contrainte de clé étrangère existante
            $table->dropForeign(['superieur_hierarchique_id']);
        });
        
        // Renommer la colonne avec DB::statement pour compatibilité
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB utilise CHANGE COLUMN
            DB::statement('ALTER TABLE profiles CHANGE superieur_hierarchique_id n_plus_1_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            // PostgreSQL utilise RENAME COLUMN
            DB::statement('ALTER TABLE profiles RENAME COLUMN superieur_hierarchique_id TO n_plus_1_id');
        } else {
            // SQLite et autres
            DB::statement('ALTER TABLE profiles RENAME COLUMN superieur_hierarchique_id TO n_plus_1_id');
        }
        
        Schema::table('profiles', function (Blueprint $table) {
            // Recréer la contrainte de clé étrangère avec le nouveau nom
            $table->foreign('n_plus_1_id')->references('id')->on('profiles')->onDelete('set null');
            
            // Ajouter la colonne N+2
            $table->foreignId('n_plus_2_id')->nullable()->after('n_plus_1_id')->constrained('profiles')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Supprimer la colonne N+2 et sa contrainte
            $table->dropForeign(['n_plus_2_id']);
            $table->dropColumn('n_plus_2_id');
            
            // Supprimer la contrainte de N+1
            $table->dropForeign(['n_plus_1_id']);
        });
        
        // Renommer la colonne en arrière avec DB::statement
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB utilise CHANGE COLUMN
            DB::statement('ALTER TABLE profiles CHANGE n_plus_1_id superieur_hierarchique_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            // PostgreSQL utilise RENAME COLUMN
            DB::statement('ALTER TABLE profiles RENAME COLUMN n_plus_1_id TO superieur_hierarchique_id');
        } else {
            // SQLite et autres
            DB::statement('ALTER TABLE profiles RENAME COLUMN n_plus_1_id TO superieur_hierarchique_id');
        }
        
        Schema::table('profiles', function (Blueprint $table) {
            // Recréer la contrainte avec l'ancien nom
            $table->foreign('superieur_hierarchique_id')->references('id')->on('profiles')->onDelete('set null');
        });
    }
};
