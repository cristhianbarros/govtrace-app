<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-007 (it. 21): el nombre de fantasía y el logo que el Administrador de
 * Organización elige para su observatorio. Aparte del nombre legal (`name`,
 * con el NIT), que solo cambia el Super Administrador (US-011, R-TA-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('display_name', 100)->nullable()->after('name');
            // En el disco "evidencias", bajo {organización}/profile/. No es evidencia: se reemplaza.
            $table->string('logo_path')->nullable()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'logo_path']);
        });
    }
};
