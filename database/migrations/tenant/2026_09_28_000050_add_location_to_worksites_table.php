<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * R-GEO-01 (it. 10): la ubicación oficial de la obra vive en la ficha de
 * GovTrace, nunca en el contrato de SECOP. Nula mientras nadie reporta
 * (Spatial-Null); el primer reporte la fija (First-Touch) y el
 * Administrador puede corregirla (US-035, it. 11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worksites', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('located_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('worksites', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'located_at']);
        });
    }
};
