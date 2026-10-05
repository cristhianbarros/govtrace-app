<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Configuration\Parameters;
use App\Domain\Configuration\ParameterValue;
use App\Domain\Geography\GeoPoint;
use App\Domain\Reports\Exceptions\EvidenceIsImmutable;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 46f — First-Touch con guardas (R-GEO-01 enmendada, US-008). El
 * primer reporte de una obra sin ubicación solo la fija si se tomó cerca de
 * la cabecera del municipio del contrato (30 km por defecto) y con una
 * precisión del GPS de 20 m o menos. Si no, el reporte se recibe igual y la
 * ubicación queda por confirmar en la Bandeja.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 * Los parámetros viven en la base central: cada test borra las versiones que agregó.
 */

// Las cabeceras, como las trae el DIVIPOLA (database/data/divipola.json).
const SANTA_MARTA_SEAT = [11.204679, -74.199829];
const EL_BANCO_SEAT = [9.008503, -73.97437];

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    $this->lastParameterVersion = (int) ParameterValue::query()->max('id');

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');

    reportableContract('CO1.PCCNTR.2222222');
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.2222222'], null);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    ParameterValue::query()->where('id', '>', $this->lastParameterVersion)->delete();
});

/** The first report of the worksite, from $meters north of $origin. */
function firstReportFrom(array $origin, float $meters, float $accuracyMeters = 10, array $overrides = []): TestResponse
{
    [$latitude, $longitude] = pointMetersNorthOf($origin, $meters);
    test()->flushSession();

    return sendReport(test()->veedor, array_merge([
        'secop_contract_id' => 'CO1.PCCNTR.2222222',
        'classification' => 'Avance',
        'latitude' => $latitude,
        'longitude' => $longitude,
        'accuracy_meters' => $accuracyMeters,
    ], $overrides));
}

function worksiteLocation(Tenant $tenant, Worksite $worksite): ?GeoPoint
{
    return $tenant->run(fn () => $worksite->fresh()->location());
}

function storedFirstReport(Tenant $tenant, TestResponse $response): Report
{
    $reportId = createdReportId($response);

    return $tenant->run(fn () => Report::query()->findOrFail($reportId));
}

it('El primer reporte solo fija la ubicación cerca del municipio del contrato', function (float $kilometers, bool $fixes) {
    $response = firstReportFrom(SANTA_MARTA_SEAT, $kilometers * 1000);

    $report = storedFirstReport($this->tenant, $response);
    $location = worksiteLocation($this->tenant, $this->worksite);

    expect($report->anchored_worksite)->toBe($fixes)
        ->and($report->distance_to_municipality_meters)->toBe((int) round($kilometers * 1000))
        ->and($location !== null)->toBe($fixes);

    if ($fixes) {
        expect($location->latitude)->toEqualWithDelta(pointMetersNorthOf(SANTA_MARTA_SEAT, $kilometers * 1000)[0], 0.0000001)
            ->and($report->anchor_withheld)->toBeNull()
            ->and($response->json('location_pending'))->toBeNull();
    } else {
        expect($report->anchor_withheld->value)->toBe('far_from_municipality')
            ->and($report->distance_to_worksite_meters)->toBeNull();
    }
})->with([
    '29 km: queda fijada en mi posición' => [29, true],
    '42.3 km: queda por confirmar' => [42.3, false],
]);

it('El veedor sabe por qué la ubicación de la obra quedó por confirmar', function () {
    firstReportFrom(SANTA_MARTA_SEAT, 42_300)
        ->assertCreated()
        ->assertJson(['location_pending' => 'La ubicación de esta obra queda por confirmar: su reporte se tomó a 42.3 km de Santa Marta, y para fijar una obra hay que estar a menos de 30 km de su municipio. La veeduría la revisará.']);
});

it('El primer reporte solo fija la ubicación con buena señal del GPS', function (float $accuracyMeters, ?string $pending) {
    $response = firstReportFrom(SANTA_MARTA_SEAT, 3_000, $accuracyMeters)->assertCreated();

    $report = storedFirstReport($this->tenant, $response);

    expect($response->json('location_pending'))->toBe($pending)
        ->and($report->anchored_worksite)->toBe($pending === null)
        ->and(worksiteLocation($this->tenant, $this->worksite) !== null)->toBe($pending === null)
        ->and($report->anchor_withheld?->value)->toBe($pending === null ? null : 'imprecise_gps');
})->with([
    '20 m: queda fijada' => [20, null],
    '21 m: queda por confirmar' => [21, 'La ubicación de esta obra queda por confirmar: la señal del GPS tenía una precisión de 21 m, y para fijar una obra se necesitan 20 m o menos. La veeduría la revisará.'],
]);

it('says first that it was far from its municipality, when it also had a poor signal', function () {
    firstReportFrom(SANTA_MARTA_SEAT, 42_300, 35)
        ->assertCreated()
        ->assertJson(['location_pending' => 'La ubicación de esta obra queda por confirmar: su reporte se tomó a 42.3 km de Santa Marta, y para fijar una obra hay que estar a menos de 30 km de su municipio. La veeduría la revisará.']);
});

it('Un contrato departamental se mide contra la cabecera más cercana de su departamento', function () {
    // La Gobernación: departamento conocido, sin municipio. El Banco está a unos 245 km de Santa Marta.
    reportableContract('CO1.PCCNTR.4444444', ['entity_name' => 'Gobernación del Magdalena', 'municipality_code' => null]);
    $departmental = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.4444444'], null);

    $report = storedFirstReport($this->tenant, firstReportFrom(EL_BANCO_SEAT, 5_000, overrides: ['secop_contract_id' => 'CO1.PCCNTR.4444444']));

    expect($report->anchored_worksite)->toBeTrue()
        ->and($report->distance_to_municipality_meters)->toBe(5_000)
        ->and(worksiteLocation($this->tenant, $departmental))->not->toBeNull();
});

it('leaves a departmental contract to confirm when it was taken far from every seat of its department', function () {
    reportableContract('CO1.PCCNTR.4444444', ['entity_name' => 'Gobernación del Magdalena', 'municipality_code' => null]);
    $departmental = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.4444444'], null);

    // Medellín, a unos 500 km.
    $response = firstReportFrom([6.246631, -75.581775], 0, overrides: ['secop_contract_id' => 'CO1.PCCNTR.4444444'])->assertCreated();

    expect(storedFirstReport($this->tenant, $response)->anchor_withheld->value)->toBe('far_from_municipality')
        ->and(worksiteLocation($this->tenant, $departmental))->toBeNull()
        ->and($response->json('location_pending'))->toStartWith('La ubicación de esta obra queda por confirmar: su reporte se tomó a ');
});

it('Mientras la ubicación esté por confirmar, el siguiente reporte lo vuelve a intentar', function () {
    $first = storedFirstReport($this->tenant, firstReportFrom(SANTA_MARTA_SEAT, 42_300));
    expect($first->anchored_worksite)->toBeFalse();

    // Otro veedor, desde cerca de Santa Marta: sin geocerca, porque la obra aún no tiene ubicación.
    $this->veedor = reportingMember($this->tenant, 'lucia@correo.co');
    $second = storedFirstReport($this->tenant, firstReportFrom(SANTA_MARTA_SEAT, 2_000));

    expect($second->anchored_worksite)->toBeTrue()
        ->and(worksiteLocation($this->tenant, $this->worksite)->latitude)->toEqualWithDelta(pointMetersNorthOf(SANTA_MARTA_SEAT, 2_000)[0], 0.0000001);
});

it('measures with the distance in force when the report was captured (R-AUD-05)', function () {
    // Se tomó sin señal a 42 km, cuando regían 30 km; al llegar ya rigen 50 km.
    Parameters::set('anchor_municipality_radius_km', '30', now()->subDays(2));
    Parameters::set('anchor_municipality_radius_km', '50', now()->subHour());

    $response = firstReportFrom(SANTA_MARTA_SEAT, 42_300, overrides: ['captured_at' => now()->subDay()->toIso8601String()])->assertCreated();

    expect(storedFirstReport($this->tenant, $response)->anchored_worksite)->toBeFalse()
        ->and($response->json('location_pending'))->toContain('a menos de 30 km de su municipio');
});

it('never lets the facts of the first report change once recorded', function (string $field, mixed $value) {
    $report = storedFirstReport($this->tenant, firstReportFrom(SANTA_MARTA_SEAT, 42_300));

    $this->tenant->run(fn () => Report::query()->findOrFail($report->id)->update([$field => $value]));
})->with([
    'why it did not fix the location' => ['anchor_withheld', null],
    'the distance to its municipality' => ['distance_to_municipality_meters', 5],
])->throws(EvidenceIsImmutable::class);
