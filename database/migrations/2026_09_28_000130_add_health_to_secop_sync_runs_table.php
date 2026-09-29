<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-014 (it. 22): lo que el panel de salud muestra de cada corrida — los
 * contratos nuevos y actualizados de cada organización (según su propio
 * territorio) y, si falló, cuándo la reintenta la cola.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secop_sync_runs', function (Blueprint $table) {
            $table->json('per_organization')->nullable(); // {organización: {inserted, updated}}
            $table->timestamp('next_retry_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('secop_sync_runs', function (Blueprint $table) {
            $table->dropColumn(['per_organization', 'next_retry_at']);
        });
    }
};
