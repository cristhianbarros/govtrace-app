<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * D12 (2026-09-28): el umbral de alerta de saldo pasa del Relayer en POL
 * (diseño EVM, 5 POL) a la cuenta patrocinadora de Stellar, la hot wallet:
 * 50 XLM, unos 200 sellos de 0,2435 XLM (medido en testnet, it. 14).
 *
 * El parámetro viejo se quita en vez de versionarse: no es otro valor del
 * mismo parámetro, es un concepto que ya no existe, y nada lo usaba.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('parameters')->where('key', 'relayer_balance_alert_threshold_pol')->delete();

        DB::table('parameters')->insert([
            'key' => 'sponsor_balance_alert_threshold_xlm',
            'value' => '50',
            'effective_from' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('parameters')->where('key', 'sponsor_balance_alert_threshold_xlm')->delete();

        DB::table('parameters')->insert([
            'key' => 'relayer_balance_alert_threshold_pol',
            'value' => '5',
            'effective_from' => now(),
            'created_at' => now(),
        ]);
    }
};
