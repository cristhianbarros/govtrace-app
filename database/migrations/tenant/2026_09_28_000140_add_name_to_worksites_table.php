<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * US-045-INT (it. 24): la ficha que agrupa varios contratos lleva el nombre
 * que le da el Administrador ("Acueducto Gaira"). Sin nombre, la vista
 * pública usa el objeto de su contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worksites', function (Blueprint $table) {
            $table->string('name', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('worksites', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
