<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-004 (it. 32): el último precio conocido de XLM, para estimar el costo
 * de las comisiones cuando el API de precios no responde (R-INT-03). Una
 * fila por moneda: solo importa el último.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xlm_price_quotes', function (Blueprint $table) {
            $table->id();
            $table->char('currency', 3)->unique();
            $table->decimal('price', 20, 7);
            $table->timestamp('quoted_at'); // la hora del precio según el API
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xlm_price_quotes');
    }
};
