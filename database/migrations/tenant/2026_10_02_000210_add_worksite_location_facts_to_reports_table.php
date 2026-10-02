<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Iteración 45f: lo que el servidor sabe de la ubicación de un reporte al
 * recibirlo — si fijó la ubicación oficial de su obra (First-Touch,
 * R-GEO-01) y a qué distancia de ella se tomó. Se escribe una vez, como el
 * resto de lo enviado (App\Domain\Reports\Report). Los reportes anteriores
 * quedan sin dato: no se reconstruye.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->boolean('anchored_worksite')->default(false);
            $table->unsignedInteger('distance_to_worksite_meters')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['anchored_worksite', 'distance_to_worksite_meters']);
        });
    }
};
