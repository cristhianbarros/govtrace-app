<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Reports\Exceptions\EvidenceIsImmutable;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Iteración 45f — lo que el servidor sabe de la ubicación de un reporte al
 * recibirlo: si fijó la ubicación oficial de su obra (First-Touch, R-GEO-01)
 * o a qué distancia de ella se tomó, medida contra la ubicación de ese
 * momento. Se escribe una vez, como el resto de lo que envió el veedor
 * (R-TA-02), para que la Bandeja lo muestre sin las coordenadas del veedor.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');

    reportableContract('CO1.PCCNTR.1234567');
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
});

function storedReport(Tenant $tenant, int $reportId): Report
{
    return $tenant->run(fn () => Report::query()->findOrFail($reportId));
}

it('records how far from the official location of its worksite a report was taken', function () {
    $reportId = sendReport($this->veedor)->assertCreated()->json('id');

    $report = storedReport($this->tenant, $reportId);

    expect($report->anchored_worksite)->toBeFalse()
        ->and($report->distance_to_worksite_meters)->toBe(120);
});

it('records that the first report of a worksite without location fixed it, at 0 m', function () {
    reportableContract('CO1.PCCNTR.7654321');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.7654321'], null);

    $reportId = sendReport($this->veedor, ['secop_contract_id' => 'CO1.PCCNTR.7654321'])->assertCreated()->json('id');

    $report = storedReport($this->tenant, $reportId);

    expect($report->anchored_worksite)->toBeTrue()
        ->and($report->distance_to_worksite_meters)->toBe(0);
});

it('never lets those facts change once recorded', function (string $field, mixed $value) {
    $reportId = sendReport($this->veedor)->assertCreated()->json('id');

    $this->tenant->run(fn () => Report::query()->findOrFail($reportId)->update([$field => $value]));
})->with([
    'whether it fixed the location' => ['anchored_worksite', true],
    'the distance' => ['distance_to_worksite_meters', 5],
])->throws(EvidenceIsImmutable::class);
