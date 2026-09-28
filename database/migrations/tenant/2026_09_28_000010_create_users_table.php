<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Per-tenant users: the Administrador de Organización and every Veedor de
 * Campo of ONE organization (R-USR-01 — the same email is a different,
 * independent account in each tenant's own database). The Super
 * Administrator never lives here; it's in the central "users" table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            // Desactivación (US-006, US-041-USR) — nunca se borra el usuario.
            $table->boolean('is_active')->default(true);
            // Invitación / alta con contraseña pendiente (US-002, US-005,
            // US-030): se guarda el hash, nunca el token en claro.
            $table->string('invitation_token_hash')->nullable();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
