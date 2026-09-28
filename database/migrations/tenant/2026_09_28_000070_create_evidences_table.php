<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-009 (it. 11): cada archivo de un reporte — una foto o el PDF — es una
 * evidencia: lo que se sella (it. 12-13), se verifica y se descarga
 * (US-024/026). Se guarda byte a byte como llegó del teléfono, sin
 * reprocesar ni difuminar (R-PRIV-05, R-PRIV-06).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained();
            $table->string('kind'); // photo | pdf
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            // R-HASH-01: recalculado por el servidor, idéntico al del teléfono.
            $table->char('sha256', 64)->index();
            $table->string('storage_path'); // en el disco "evidencias" (S3)
            // pending: en cola para el sellado; el lote de Merkle de la it. 13 la recoge.
            $table->string('seal_status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidences');
    }
};
