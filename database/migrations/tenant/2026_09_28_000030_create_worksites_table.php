<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-034 (it. 9): la ficha de obra de GovTrace, por organización — vive
 * en la base de CADA tenant (specs/SPEC.md: "ficha de obra (ubicación,
 * riesgo) por organización"), separada del contrato SECOP (central,
 * inmutable — R-SEC-01).
 *
 * secop_contract_id sin FK a propósito: "contracts" vive en la base
 * central, esta tabla en la del tenant (bases físicamente distintas).
 *
 * Por ahora una ficha ancla exactamente un contrato; agrupar varios
 * contratos en una misma ficha es una acción explícita del Admin de
 * Organización (US-045-INT, R-INT-05) que llega en la it. 29.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worksites', function (Blueprint $table) {
            $table->id();
            $table->string('secop_contract_id')->unique();
            // US-034: calculado a diario (App\Jobs\CalculateWorksitesAtRisk)
            // — nunca se toca el contrato de SECOP para guardar esto.
            $table->boolean('at_risk')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worksites');
    }
};
