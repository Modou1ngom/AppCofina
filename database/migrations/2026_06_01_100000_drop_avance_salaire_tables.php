<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('avance_salaire_integration_lignes');
        Schema::dropIfExists('avance_salaire_integrations');
        Schema::dropIfExists('avance_salaire_demandes');
        Schema::dropIfExists('avance_salaire_baremes');
    }

    public function down(): void
    {
        // Tables recréées par les migrations historiques du module si besoin de rollback manuel.
    }
};
