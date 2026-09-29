<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * It. 39: Stellar admite una sola transacción pendiente por cuenta, así que
 * la cuenta selladora sella por turnos (App\Infrastructure\Stellar\SealerTurn).
 * Central: hay una sola selladora para todas las organizaciones. Una fila por
 * cuenta selladora: la transacción suya que espera un ledger, si hay una, y
 * hasta cuándo el turno está tomado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sealer_turns', function (Blueprint $table) {
            $table->string('account', 56)->primary();
            $table->string('tx_hash', 64)->nullable();
            $table->timestamp('taken_until')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sealer_turns');
    }
};
