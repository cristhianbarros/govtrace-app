<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-008 (it. 10): lo que un veedor envía desde la obra. Los archivos y
 * sus hashes llegan con US-009 (it. 11); el sellado, en las it. 12-13.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // el veedor
            $table->foreignId('worksite_id')->constrained(); // la ficha completa, con todos sus contratos
            $table->string('classification'); // Avance | Retraso | Abandono
            $table->text('comment')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_meters', 6, 2);
            // R-AUD-05: el radio de geocerca vigente al capturar, el mismo
            // con el que se validó el reporte.
            $table->unsignedInteger('geofence_radius_meters');
            $table->timestamp('captured_at'); // reloj del teléfono
            $table->timestamp('received_at'); // reloj del servidor
            // R-SEC-05 / R-MON-02: solo se marca; decide el Administrador.
            $table->boolean('suspicious_capture_time')->default(false);
            $table->timestamps();

            $table->index(['worksite_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
