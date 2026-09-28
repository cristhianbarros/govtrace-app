<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * R-AUD-04: quién, cuándo, qué acción, valor anterior y nuevo. Central,
 * no por tenant: el Super Administrador (US-043-MON, it. 21) necesita ver
 * el de toda la plataforma sin abrir cada base de datos. Las acciones
 * que ocurren DENTRO de un tenant (publicar/rechazar evidencia, invitar
 * veedor...) también escriben aquí, identificadas por organization_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->nullable();
            $table->string('actor_type')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at');

            $table->foreign('organization_id')->references('id')->on('tenants')->onDelete('set null');
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
