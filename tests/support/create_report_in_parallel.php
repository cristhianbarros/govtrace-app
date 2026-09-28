<?php

/*
 * Soporte de tests/Feature/Reports/FirstTouchRaceTest.php (it. 10): crea
 * un reporte desde OTRO proceso — otra conexión a Postgres, otra
 * transacción — para que la carrera de First-Touch sea real y no una
 * simulación. Hereda el entorno del proceso de Pest (DB_DATABASE=testing).
 *
 * Uso: php tests/support/create_report_in_parallel.php <tenant> <veedor> <contrato> <lat> <lng>
 * Imprime una línea JSON: {"status":"accepted","report_id":…} o {"status":"rejected","message":…}.
 */

use App\Application\Reports\CreateReport;
use App\Application\Reports\NewReport;
use App\Domain\Organization\User;
use App\Domain\Reports\Exceptions\ReportValidationException;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $tenantId, $veedorId, $secopContractId, $latitude, $longitude] = $argv;

$result = Tenant::query()->findOrFail($tenantId)->run(function () use ($veedorId, $secopContractId, $latitude, $longitude) {
    try {
        $report = (new CreateReport)->handle(User::query()->findOrFail($veedorId), new NewReport(
            secopContractId: $secopContractId,
            classification: 'Avance',
            comment: null,
            latitude: (float) $latitude,
            longitude: (float) $longitude,
            accuracyMeters: 10.0,
            capturedAt: now(),
        ));

        return ['status' => 'accepted', 'report_id' => $report->id];
    } catch (ReportValidationException $e) {
        return ['status' => 'rejected', 'message' => $e->getMessage()];
    }
});

echo json_encode($result);
