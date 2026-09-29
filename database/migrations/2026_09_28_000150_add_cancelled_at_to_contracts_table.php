<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-018 (it. 30): cuándo vio GovTrace que SECOP anuló el contrato. Un
 * reporte capturado antes, que esperó sin señal en el teléfono, sigue
 * entrando. También en el archivo (US-048-MNT), que guarda las mismas
 * columnas. Los ya anulados quedan sin fecha: anulados desde siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['contracts', 'archived_contracts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('cancelled_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['contracts', 'archived_contracts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('cancelled_at');
            });
        }
    }
};
