<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-021 (it. 22): los intentos de sellado los cuenta cada sello, no la
 * cola — una pausa por falta de saldo no es una falla. Tras el quinto
 * intento fallido, "Falla de Sellado" (status "failed"). stuck_alerted_at:
 * la alerta de cola estancada sale una sola vez por evidencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_seals', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable(); // para el soporte técnico; el veedor nunca lo ve
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('stuck_alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('report_seals', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'last_error', 'failed_at', 'stuck_alerted_at']);
        });
    }
};
