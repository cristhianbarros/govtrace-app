<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Lo mínimo que US-013 necesita escribir para que US-014 (panel de
 * salud, it. 14) tenga datos reales que mostrar: última corrida, si
 * falló, cuántos contratos procesó y cuántos descartó por no emparejar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secop_sync_runs', function (Blueprint $table) {
            $table->id();
            // null = corrida nocturna de todos los territorios; un id = la
            // sincronización inmediata de esa organización (US-013).
            $table->string('organization_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status'); // running | success | failed
            $table->unsignedInteger('contracts_inserted')->default(0);
            $table->unsignedInteger('contracts_updated')->default(0);
            // Descartados por no emparejar con DIVIPOLA (R-INT-03) y cuáles:
            // {"Magdalena / No Definido": 1, …} — lo que el panel reporta.
            $table->unsignedInteger('contracts_discarded')->default(0);
            $table->json('unmatched_locations')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secop_sync_runs');
    }
};
