<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * R-BLK-05 (it. 13): cada archivo guarda su lugar en el árbol de Merkle del
 * reporte y su prueba de inclusión, que se publica para que el navegador del
 * Verificador recomponga la raíz. El estado del sellado pasa al reporte
 * (report_seals): una raíz por reporte, un solo estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->unsignedSmallInteger('leaf_index')->nullable();
            $table->json('merkle_proof')->nullable();
            $table->dropColumn('seal_status');
        });
    }

    public function down(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->string('seal_status')->default('pending');
            $table->dropColumn(['leaf_index', 'merkle_proof']);
        });
    }
};
