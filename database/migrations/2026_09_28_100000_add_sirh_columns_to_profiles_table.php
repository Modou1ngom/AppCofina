<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('matricule_sirh', 64)->nullable()->after('matricule');
            $table->string('entite')->nullable()->after('nom');
            $table->string('nationalite', 100)->nullable()->after('entite');
            $table->string('genre', 20)->nullable()->after('site');
            $table->date('date_naissance')->nullable()->after('genre');
            $table->string('diplome')->nullable()->after('date_naissance');
            $table->unsignedSmallInteger('age')->nullable()->after('diplome');
            $table->string('situation_matrimoniale', 100)->nullable()->after('age');
            $table->unsignedSmallInteger('nombre_enfants')->nullable()->after('situation_matrimoniale');
            $table->string('numero_cni', 64)->nullable()->after('nombre_enfants');
            $table->string('categorie', 100)->nullable()->after('fonction');
            $table->string('duree_contrat', 100)->nullable()->after('type_contrat');
            $table->date('date_debut_contrat')->nullable()->after('duree_contrat');
            $table->date('date_fin_contrat')->nullable()->after('date_debut_contrat');
            $table->date('date_embauche')->nullable()->after('date_fin_contrat');
            $table->boolean('dossier_a_jour')->nullable()->after('date_entree');
            $table->string('anciennete', 100)->nullable()->after('dossier_a_jour');
            $table->string('grade', 100)->nullable()->after('motif_depart');
            $table->string('h', 50)->nullable()->after('grade');
            $table->string('numero_carte_assurance', 64)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'matricule_sirh',
                'entite',
                'nationalite',
                'genre',
                'date_naissance',
                'diplome',
                'age',
                'situation_matrimoniale',
                'nombre_enfants',
                'numero_cni',
                'categorie',
                'duree_contrat',
                'date_debut_contrat',
                'date_fin_contrat',
                'date_embauche',
                'dossier_a_jour',
                'anciennete',
                'grade',
                'h',
                'numero_carte_assurance',
            ]);
        });
    }
};
