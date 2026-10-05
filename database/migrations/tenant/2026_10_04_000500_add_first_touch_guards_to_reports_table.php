<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Iteración 46f (R-GEO-01 enmendada): lo que el servidor midió cuando un
 * reporte llegó a una obra sin ubicación — contra qué cabecera municipal, a
 * qué distancia, y por qué no la fijó (lejos de su municipio, o con mala
 * señal), si no la fijó. Se escribe una vez, como el resto de lo enviado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('anchor_withheld')->nullable();
            $table->string('reference_municipality_code', 5)->nullable();
            $table->unsignedInteger('distance_to_municipality_meters')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['anchor_withheld', 'reference_municipality_code', 'distance_to_municipality_meters']);
        });
    }
};
