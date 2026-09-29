<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-048-MNT (it. 25, R-MNT-04): los contratos cerrados hace más de 5 años
 * que ninguna organización tiene en una ficha de obra salen de "contracts"
 * y quedan aquí, tal cual, con su mismo id. Vuelven si llega un reporte o
 * si SECOP los reabre. Sin claves foráneas: un archivo no depende de nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // el que tenía en "contracts"
            $table->string('secop_contract_id')->unique();
            $table->string('process_number')->nullable();
            $table->string('entity_name');
            $table->text('contractor_name')->nullable();
            $table->text('object')->nullable();
            $table->string('contract_type');
            $table->decimal('value', 18, 2)->nullable();
            $table->date('signed_at')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status');
            $table->string('department_code', 2);
            $table->string('municipality_code', 5)->nullable();
            $table->text('secop_url')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_contracts');
    }
};
