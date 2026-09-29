<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-020b (it. 13): el sellado de un reporte en Stellar — una raíz de
 * Merkle por reporte (R-BLK-05), así que el estado vive aquí y no en cada
 * archivo. Recibida → En Cola → Transmitiendo → Sellada; cada paso guarda
 * su hora (R-MON-01 mide las 2 h "En Cola" con queued_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_seals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->unique()->constrained();
            $table->string('status'); // received | queued | transmitting | sealed
            $table->char('merkle_root', 64)->nullable()->index();
            // SHA-256 de «organización:ficha» (R-BLK-02): va a la red junto a la raíz.
            $table->char('worksite_reference', 64)->nullable();
            // El JSON canónico sellado, tal cual. Se publica su hash, no el JSON (R-PRIV-03).
            $table->text('metadata_json')->nullable();
            $table->string('tx_hash')->nullable();
            $table->unsignedInteger('ledger')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('transmitted_at')->nullable();
            $table->timestamp('sealed_at')->nullable(); // hora del ledger que lo incluyó
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_seals');
    }
};
