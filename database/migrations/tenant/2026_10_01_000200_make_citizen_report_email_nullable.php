<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Iteración 45c: el correo de un informe atendido se borra a los 30 días
 * (App\Jobs\PurgeCitizenReports); el informe queda, sin correo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->text('email')->nullable()->change();
            $table->string('email_hash', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('citizen_reports', function (Blueprint $table) {
            $table->text('email')->nullable(false)->change();
            $table->string('email_hash', 64)->nullable(false)->change();
        });
    }
};
