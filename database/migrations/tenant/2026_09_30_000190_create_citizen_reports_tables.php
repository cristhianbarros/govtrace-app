<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-059-LEG (it. 44f): lo que un ciudadano le informa a la veeduría (Ley 850
 * de 2003, art. 18 a)) y los códigos con que verifica su correo. El correo se
 * guarda cifrado (email) y su huella HMAC (email_hash) cuenta los límites: la
 * veeduría nunca lo ve (R-LEG-10). No se sella ni se publica (R-LEG-09).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizen_report_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email_hash', 64)->index();
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('data_authorized_at');
            $table->timestamps();
        });

        Schema::create('citizen_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksite_id')->constrained();
            $table->text('email');
            $table->string('email_hash', 64)->index();
            $table->text('message');
            $table->string('photo_path')->nullable();
            $table->string('status')->default('new');
            $table->text('answer')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->timestamp('data_authorized_at');
            $table->string('data_policy_version');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_reports');
        Schema::dropIfExists('citizen_report_codes');
    }
};
