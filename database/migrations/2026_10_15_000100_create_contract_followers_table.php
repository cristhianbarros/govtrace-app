<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * It. 59 (US-066): en la base central, qué veedurías siguen qué contratos —
 * tienen su ficha de obra — y cuántas evidencias publicadas tiene esa ficha,
 * con la fecha de la última publicación. Las fichas viven en la base de cada
 * veeduría; este resumen evita abrirlas todas en cada búsqueda del Inicio. Se
 * recalcula entero cada noche (contracts:followers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_followers', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('secop_contract_id');
            $table->unsignedInteger('published_evidences')->default(0);
            $table->timestamp('last_published_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'secop_contract_id']);
            $table->index('secop_contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_followers');
    }
};
