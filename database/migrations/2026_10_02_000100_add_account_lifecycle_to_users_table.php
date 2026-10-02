<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Iteración 46a (US-063-USR): el ciclo de vida de una cuenta de Super
 * Administrador, como el de los miembros de una organización — se invita
 * (sin contraseña hasta activar la cuenta), se activa autorizando el
 * tratamiento de sus datos (US-058-LEG), y se desactiva o reactiva. Los
 * que ya existen quedan activos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->boolean('is_active')->default(true);
            $table->string('invitation_token_hash', 64)->nullable();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->timestamp('data_authorized_at')->nullable();
            $table->string('data_policy_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'invitation_token_hash', 'invitation_expires_at', 'data_authorized_at', 'data_policy_version']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
