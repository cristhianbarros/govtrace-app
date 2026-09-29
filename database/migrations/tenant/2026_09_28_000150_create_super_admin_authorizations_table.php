<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-042-SEC (it. 25, R-SA-02): cada vez que el Administrador autoriza al
 * Super Administrador a reportar en nombre de la organización — 30 días,
 * revocable. Se conservan todas: quién la dio, cuándo y quién la revocó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_admin_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('granted_by')->constrained('users');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['revoked_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_authorizations');
    }
};
