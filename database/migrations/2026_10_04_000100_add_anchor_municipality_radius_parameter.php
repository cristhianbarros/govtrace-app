<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Iteración 46f (R-GEO-01 enmendada, US-038-CFG): el primer reporte de una
 * obra sin ubicación solo la fija si se tomó a menos de esta distancia de la
 * cabecera del municipio del contrato. Un parámetro más del Super
 * Administrador, con su primera versión: rige también para lo capturado
 * antes (Parameters::valueAt responde con la primera versión).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('parameters')->where('key', 'anchor_municipality_radius_km')->exists()) {
            return;
        }

        DB::table('parameters')->insert([
            'key' => 'anchor_municipality_radius_km',
            'value' => '30',
            'effective_from' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('parameters')->where('key', 'anchor_municipality_radius_km')->delete();
    }
};
