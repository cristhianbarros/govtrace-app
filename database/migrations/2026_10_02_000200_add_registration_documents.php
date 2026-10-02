<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Iteración 46b (US-062-ALT): el PDF de la resolución o del certificado de
 * inscripción de una veeduría. Va con su solicitud de alta mientras se
 * decide; si se aprueba, pasa a la organización (RegistrationDocuments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_requests', function (Blueprint $table) {
            $table->string('document_path')->nullable();
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('registration_document_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organization_requests', fn (Blueprint $table) => $table->dropColumn('document_path'));
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('registration_document_path'));
    }
};
