<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * US-038-CFG (it. 21 builds the panel) + R-AUD-05 ("los reportes se
 * validan con el valor vigente en el momento de la captura"). A new row
 * per change, never an UPDATE — that's what makes "el valor vigente en
 * una fecha pasada" answerable at all (App\Domain\Configuration\Parameters).
 *
 * Central, not per-tenant: today every organization shares the same
 * values (R-CFG-02, "ningún parámetro es configurable por organización").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parameters', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('value');
            $table->timestamp('effective_from');
            $table->timestamp('created_at');

            $table->index(['key', 'effective_from']);
        });

        $defaults = [
            'geofence_radius_meters' => '500',
            'closed_contract_report_window_months' => '12',
            'invitation_validity_hours' => '48',
            'relayer_balance_alert_threshold_pol' => '5',
            'secop_sync_hour' => '02:00',
        ];

        $now = now();

        foreach ($defaults as $key => $value) {
            DB::table('parameters')->insert([
                'key' => $key,
                'value' => $value,
                'effective_from' => $now,
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parameters');
    }
};
