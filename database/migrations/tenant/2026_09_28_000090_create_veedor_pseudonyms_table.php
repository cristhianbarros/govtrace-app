<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * D7 / R-PRIV-03 (it. 13): el JSON sellado lleva un seudónimo del veedor,
 * nunca su ID. El seudónimo es un HMAC, así que no se puede invertir; esta
 * tabla es la única forma de volver del seudónimo al veedor, y se conserva
 * 5 años (R-MNT-03; la purga es de la it. 35).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veedor_pseudonyms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->char('pseudonym', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veedor_pseudonyms');
    }
};
