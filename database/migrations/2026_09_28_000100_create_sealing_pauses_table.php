<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-020b (it. 13): el sellado se pausa cuando la cuenta patrocinadora se
 * queda sin XLM y se reanuda solo cuando vuelve a tener. Central: hay una
 * sola patrocinadora para todas las organizaciones. Una fila por pausa, así
 * queda el historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sealing_pauses', function (Blueprint $table) {
            $table->id();
            $table->string('reason');
            $table->timestamp('paused_at');
            $table->timestamp('resumed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sealing_pauses');
    }
};
