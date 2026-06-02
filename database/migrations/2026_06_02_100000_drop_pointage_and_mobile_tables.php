<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pointage_declarations');
        Schema::dropIfExists('pointages');
        Schema::dropIfExists('pointage_sites');
        Schema::dropIfExists('personal_access_tokens');
    }

    public function down(): void
    {
        // Recréation via les migrations historiques du module si rollback manuel.
    }
};
