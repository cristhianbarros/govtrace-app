<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Central, compartida por todas las organizaciones (decisión de tenancy,
 * specs/SPEC.md) — una sola copia de cada contrato de SECOP II, cada
 * organización ve los de su territorio por consulta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            // El identificador oficial de SECOP II (US-033) — nunca se
            // borra ni se reutiliza; la restricción de unicidad es la
            // que impide duplicados en el upsert.
            $table->string('secop_contract_id')->unique();
            $table->string('process_number')->nullable();
            $table->string('entity_name');
            // text: SECOP names consortia at length ("CONSORCIO
            // IMPLEMENTACION DE SEÑALIZACION VIAL EN EL MUNICIPIO DE…").
            $table->text('contractor_name')->nullable();
            $table->text('object')->nullable();
            $table->string('contract_type');
            $table->decimal('value', 18, 2)->nullable();
            $table->date('signed_at')->nullable();
            $table->date('end_date')->nullable();
            // Tal cual lo reporta SECOP (p. ej. "En ejecución", "Terminado")
            // salvo "cancelled": ese es nuestro marcador interno cuando
            // SECOP retira o anula el contrato (R-SEC-01, US-033).
            $table->string('status');
            $table->string('department_code', 2);
            $table->string('municipality_code', 5);
            $table->text('secop_url')->nullable();
            // El payload crudo de SECOP, para auditoría/depuración — un
            // proyecto Open Source se beneficia de poder mostrar
            // exactamente lo que se recibió.
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->foreign('department_code')->references('code')->on('departments');
            $table->foreign('municipality_code')->references('code')->on('municipalities');
            $table->index(['department_code', 'municipality_code']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
