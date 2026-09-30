<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-058-LEG (it. 44e): la prueba de la autorización del tratamiento de datos
 * (Ley 1581 de 2012, art. 9) — cuándo la dio cada persona y qué versión de la
 * política aceptó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('data_authorized_at')->nullable();
            $table->string('data_policy_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['data_authorized_at', 'data_policy_version']);
        });
    }
};
