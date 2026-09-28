<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-012: en la base central, no en cada tenant — la sincronización SECOP
 * (it. 7) necesita consultar el territorio de TODAS las organizaciones
 * activas sin tener que abrir cada base de datos por separado.
 * Una fila = un departamento O un municipio (nunca ambos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_territories', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('department_code', 2)->nullable();
            $table->string('municipality_code', 5)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('department_code')->references('code')->on('departments');
            $table->foreign('municipality_code')->references('code')->on('municipalities');
            // No UNIQUE aquí a propósito: Postgres trata NULL <> NULL, así
            // que una restricción sobre estas dos columnas nullable no
            // evitaría duplicados de un mismo municipio. Lo valida
            // ConfigureTerritory antes de insertar.
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_territories');
    }
};
