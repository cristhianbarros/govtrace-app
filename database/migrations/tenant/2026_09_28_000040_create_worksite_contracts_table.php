<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * US-008 (it. 10): "si la ficha de obra agrupa varios contratos, el
 * reporte se vincula a la ficha completa" (R-INT-05). La it. 9 dejó un
 * solo secop_contract_id en la ficha; pasa a esta tabla. Agrupar
 * contratos como acción del Administrador sigue siendo US-045-INT (it. 29).
 *
 * secop_contract_id único: dentro de una organización, un contrato
 * pertenece a una sola ficha. Esa unicidad es además la que resuelve la
 * carrera de dos veedores creando a la vez la ficha del mismo contrato.
 * Sin FK a "contracts": vive en la base central, esta en la del tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worksite_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksite_id')->constrained()->cascadeOnDelete();
            $table->string('secop_contract_id')->unique();
            $table->timestamps();
        });

        DB::table('worksite_contracts')->insertUsing(
            ['worksite_id', 'secop_contract_id', 'created_at', 'updated_at'],
            DB::table('worksites')->select('id', 'secop_contract_id', 'created_at', 'updated_at'),
        );

        Schema::table('worksites', function (Blueprint $table) {
            $table->dropColumn('secop_contract_id');
        });
    }

    public function down(): void
    {
        Schema::table('worksites', function (Blueprint $table) {
            $table->string('secop_contract_id')->nullable()->unique();
        });

        // Cada ficha vuelve a quedarse con uno solo de sus contratos.
        DB::statement('update worksites set secop_contract_id = (select min(secop_contract_id) from worksite_contracts where worksite_id = worksites.id)');

        Schema::dropIfExists('worksite_contracts');
    }
};
