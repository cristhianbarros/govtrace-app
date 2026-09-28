<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-015/US-016 (it. 8): "un departamento incluye la Gobernación y todos
 * sus municipios". SECOP publica los contratos de una Gobernación con
 * departamento conocido y ciudad "No Definido" — no tienen municipio.
 *
 * La it. 7 los descartaba como no emparejados (decisión pendiente
 * documentada en specs/PLAN.md); esta migración la resuelve: quedan
 * como contratos departamentales (municipality_code nulo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['municipality_code']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->string('municipality_code', 5)->nullable()->change();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('municipality_code')->references('code')->on('municipalities');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['municipality_code']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->string('municipality_code', 5)->nullable(false)->change();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('municipality_code')->references('code')->on('municipalities');
        });
    }
};
