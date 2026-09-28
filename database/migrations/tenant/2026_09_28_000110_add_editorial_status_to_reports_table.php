<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-036 / US-037 (it. 15): el estado editorial de cada evidencia, aparte
 * de su estado de sellado. Nace "Oculto"; el Administrador la publica, la
 * rechaza o, ya publicada, la retira. Nada se borra (revocación lógica).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('editorial_status')->default('hidden')->index(); // hidden | published | rejected | withdrawn
            // El motivo de un rechazo o de un retiro; el log de auditoría guarda además quién y cuándo.
            $table->text('editorial_reason')->nullable();
            $table->timestamp('editorial_decided_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['editorial_status', 'editorial_reason', 'editorial_decided_at']);
        });
    }
};
