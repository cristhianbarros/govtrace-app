<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * It. 44d (R-LEG-06): una veeduría que forman unos ciudadanos se inscribe en
 * la personería o en la cámara de comercio (Ley 850 de 2003, art. 3) y puede
 * no tener NIT. El NIT pasa a opcional, y la inscripción — el número de la
 * resolución o el acta y la entidad que la registró — es la alternativa. La
 * base misma no deja una organización sin ninguno de los dos.
 *
 * registration_key: la inscripción sin mayúsculas, tildes ni espacios de
 * más, para que la misma no se registre dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('nit')->nullable()->change();
            $table->string('registration_number')->nullable();
            $table->string('registration_authority')->nullable();
            $table->string('registration_key')->nullable()->unique();
        });

        DB::statement('ALTER TABLE tenants ADD CONSTRAINT tenants_nit_or_registration CHECK (nit IS NOT NULL OR registration_key IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_nit_or_registration');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['registration_key']);
            $table->dropColumn(['registration_number', 'registration_authority', 'registration_key']);
            $table->string('nit')->nullable(false)->change();
        });
    }
};
