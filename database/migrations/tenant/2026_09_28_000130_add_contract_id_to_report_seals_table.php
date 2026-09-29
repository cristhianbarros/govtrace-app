<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * US-023 / US-026 (it. 23): el contrato que guarda cada sello — va en el
 * recibo y en la prueba de inclusión. Por sello, no de la configuración: si
 * algún día se despliega otro contrato, los sellos anteriores siguen en el
 * suyo. Los que ya estaban sellados lo fueron en el contrato configurado hoy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_seals', function (Blueprint $table) {
            $table->string('contract_id', 56)->nullable();
        });

        DB::table('report_seals')->whereNotNull('ledger')->update(['contract_id' => config('stellar.sealing_contract_id')]);
    }

    public function down(): void
    {
        Schema::table('report_seals', function (Blueprint $table) {
            $table->dropColumn('contract_id');
        });
    }
};
