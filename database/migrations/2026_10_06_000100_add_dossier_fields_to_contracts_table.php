<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Iteración 46j: lo que el expediente de una obra le dice a la Contraloría —
 * el nombre del supervisor (nunca su documento), el orden de la entidad y el
 * origen de los recursos, en las seis fuentes que SECOP II desglosa —. También
 * en el archivo (US-048-MNT), que guarda las mismas columnas. Los contratos
 * ya guardados se llenan con la próxima sincronización.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['contracts', 'archived_contracts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('supervisor_name')->nullable();
                $table->string('entity_order', 40)->nullable();
                $table->json('funding_sources')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['contracts', 'archived_contracts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['supervisor_name', 'entity_order', 'funding_sources']);
            });
        }
    }
};
