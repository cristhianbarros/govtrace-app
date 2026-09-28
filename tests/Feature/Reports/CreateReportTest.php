<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Configuration\Parameters;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
 * Iteración 10 — Crear reporte: GPS, geocerca y First-Touch
 * (specs/PLAN.md). Traduce features/US-008.feature contra el endpoint
 * real, POST /reports en el subdominio de la organización. El escenario
 * de los dos veedores simultáneos vive en FirstTouchRaceTest (dos
 * transacciones reales, en dos procesos).
 *
 * Cada reporte viaja con 1 foto y su SHA-256 (sendReport); las reglas de
 * los archivos son US-009 y viven en ReportEvidenceTest (it. 11). El
 * sellado llega en las it. 12-13. Los textos de la PWA (el mensaje de éxito, "reintentar hasta obtener
 * buena señal") son de la it. 16: el backend responde 201, o 422 con el
 * motivo.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');

    // Antecedentes: carlos@correo.co, veedor de una organización que
    // vigila Magdalena; la obra del contrato CO1.PCCNTR.1234567 ya tiene
    // ubicación oficial; la geocerca vigente es la de por defecto, 500 m.
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

    // Devuelve cada parámetro a su primera versión (la de por defecto).
    DB::table('parameters')->whereNotIn('id', DB::table('parameters')->selectRaw('min(id)')->groupBy('key'))->delete();
});

function storedReports(Tenant $tenant): Collection
{
    return $tenant->run(fn () => Report::query()->orderBy('id')->get());
}

it('accepts a report close to the worksite, with the position, classification and comment', function () {
    [$latitude, $longitude] = pointMetersNorthOf(santaMartaWorksiteLocation(), 120);

    $response = sendReport($this->veedor, ['latitude' => $latitude, 'longitude' => $longitude, 'accuracy_meters' => 15]);

    $response->assertCreated();
    $report = storedReports($this->tenant)->sole();

    expect($response->json('id'))->toBe($report->id)
        ->and($report->worksite_id)->toBe($this->worksite->id)
        ->and($report->user_id)->toBe($this->veedor->id)
        ->and($report->classification->value)->toBe('Retraso')
        ->and($report->comment)->toBe('Obra detenida hace 2 meses')
        ->and((float) $report->latitude)->toEqualWithDelta($latitude, 0.0000001)
        ->and((float) $report->longitude)->toEqualWithDelta($longitude, 0.0000001)
        ->and((float) $report->accuracy_meters)->toBe(15.0)
        ->and($report->received_at)->not->toBeNull();
});

it('lets the first report anchor the official location of a worksite that has none (First-Touch)', function (bool $worksiteAlreadyExists) {
    $contract = reportableContract('CO1.PCCNTR.2222222');
    if ($worksiteAlreadyExists) {
        worksiteWithContracts($this->tenant, ['CO1.PCCNTR.2222222'], null);
    }

    sendReport($this->veedor, [
        'secop_contract_id' => 'CO1.PCCNTR.2222222',
        'classification' => 'Avance',
        'latitude' => 11.2408,
        'longitude' => -74.1990,
        'accuracy_meters' => 10,
    ])->assertCreated();

    $worksites = $this->tenant->run(fn () => Worksite::query()
        ->whereHas('contracts', fn ($query) => $query->where('secop_contract_id', 'CO1.PCCNTR.2222222'))
        ->get());

    // La ubicación vive en la ficha de obra de la organización; el
    // contrato de SECOP no se toca y ni siquiera tiene dónde guardarla.
    expect($worksites)->toHaveCount(1)
        ->and((float) $worksites->first()->latitude)->toBe(11.2408)
        ->and((float) $worksites->first()->longitude)->toBe(-74.199)
        ->and(Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.2222222')->first()->updated_at)->toEqual($contract->updated_at)
        ->and(Schema::hasColumn('contracts', 'latitude'))->toBeFalse();
})->with([
    'la ficha todavía no existe' => [false],
    'la ficha existe sin ubicación' => [true],
]);

it('requires a classification', function () {
    sendReport($this->veedor, ['classification' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('classification');

    expect(storedReports($this->tenant))->toBeEmpty();
});

it('only accepts the defined classifications', function (string $clasificacion, bool $aceptada) {
    $response = sendReport($this->veedor, ['classification' => $clasificacion]);

    $aceptada
        ? $response->assertCreated()
        : $response->assertUnprocessable()->assertJsonValidationErrors('classification');
})->with([
    'Avance' => ['Avance', true],
    'Retraso' => ['Retraso', true],
    'Abandono' => ['Abandono', true],
    'Anomalía' => ['Anomalía', false],
]);

it('takes an optional comment of at most 500 characters', function (int $largo, bool $aceptado) {
    $response = sendReport($this->veedor, ['classification' => 'Avance', 'comment' => str_repeat('a', $largo)]);

    $aceptado
        ? $response->assertCreated()
        : $response->assertUnprocessable()->assertJsonValidationErrors('comment');
})->with([
    '0 caracteres' => [0, true],
    '500 caracteres' => [500, true],
    '501 caracteres' => [501, false],
]);

it('requires a GPS accuracy of 50 m or better', function (int $precision, bool $seEnvia) {
    $response = sendReport($this->veedor, ['accuracy_meters' => $precision]);

    $seEnvia
        ? $response->assertCreated()
        : $response->assertUnprocessable()->assertJsonValidationErrors('accuracy_meters');
})->with([
    '50 m' => [50, true],
    '51 m' => [51, false],
]);

it('cannot create a report without the device location', function () {
    sendReport($this->veedor, ['latitude' => null, 'longitude' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['location' => 'GovTrace requiere acceso a su ubicación exacta para certificar criptográficamente que la evidencia fue tomada en el sitio de la obra. Por favor habilite el GPS.']);

    expect(storedReports($this->tenant))->toBeEmpty();
});

it('rejects a report taken outside the geofence of the worksite', function () {
    [$latitude, $longitude] = pointMetersNorthOf(santaMartaWorksiteLocation(), 2300);

    sendReport($this->veedor, ['classification' => 'Abandono', 'latitude' => $latitude, 'longitude' => $longitude, 'accuracy_meters' => 10])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['location' => 'Se encuentra a 2.3 km de la ubicación oficial de la obra. Para prevenir fraudes, debe acercarse a un radio de 500 metros del proyecto.']);

    expect(storedReports($this->tenant))->toBeEmpty();
});

it('rejects a report on a contract outside the territory of the organization', function () {
    reportableContract('CO1.PCCNTR.MEDELLIN', ['department_code' => '05', 'municipality_code' => '05001']);

    sendReport($this->veedor, ['secop_contract_id' => 'CO1.PCCNTR.MEDELLIN'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('secop_contract_id');

    expect(storedReports($this->tenant))->toBeEmpty();
});

it('flags a report whose capture time is in the future or older than the 7 days of offline validity', function (string $captura, bool $marcado) {
    $this->travelTo(Carbon::parse('2026-09-27 10:00:00'));

    sendReport($this->veedor, ['captured_at' => Carbon::parse($captura)->toIso8601String()])->assertCreated();

    // R-MON-02: solo se marca, nunca se rechaza — decide el Administrador
    // al revisar (su bandeja es la it. 15; el panel global, la it. 19).
    $report = storedReports($this->tenant)->sole();

    expect($report->suspicious_capture_time)->toBe($marcado)
        ->and($report->received_at->toDateTimeString())->toBe('2026-09-27 10:00:00');
})->with([
    '20 minutos antes' => ['2026-09-27 09:40', false],
    '6 días antes' => ['2026-09-21 10:00', false],
    // R-SEC-05: 5 minutos de tolerancia hacia el futuro para la latencia y
    // el reloj desfasado de los teléfonos (como el leeway de un JWT).
    '4 minutos en el futuro' => ['2026-09-27 10:04', false],
    'justo 5 minutos en el futuro' => ['2026-09-27 10:05', false],
    '6 minutos en el futuro' => ['2026-09-27 10:06', true],
    '2 horas en el futuro' => ['2026-09-27 12:00', true],
    'más de 7 días antes' => ['2026-09-19 09:00', true],
]);

it('links a report to the whole worksite when the worksite groups several contracts', function () {
    // "Acueducto Gaira": agrupar contratos en una ficha es una acción del
    // Administrador (US-045-INT, it. 29); aquí la ficha ya viene agrupada.
    reportableContract('CO1.PCCNTR.1111111');
    reportableContract('CO1.PCCNTR.3333333');
    $gaira = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'], santaMartaWorksiteLocation());

    sendReport($this->veedor, ['secop_contract_id' => 'CO1.PCCNTR.1111111'])->assertCreated();

    $report = storedReports($this->tenant)->sole();
    $contracts = $this->tenant->run(fn () => $report->worksite->contracts->pluck('secop_contract_id')->sort()->values()->all());

    expect($report->worksite_id)->toBe($gaira->id)
        ->and($contracts)->toBe(['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333']);
});

it('lets veedores at the real site report once a wrong official location is corrected', function () {
    // La ubicación quedó fijada a 3 km del sitio real.
    $realSite = santaMartaWorksiteLocation();
    [$wrongLatitude, $wrongLongitude] = pointMetersNorthOf($realSite, 3000);
    $this->tenant->run(fn () => $this->worksite->update(['latitude' => $wrongLatitude, 'longitude' => $wrongLongitude]));

    sendReport($this->veedor, ['latitude' => $realSite[0], 'longitude' => $realSite[1]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('location');

    // El Administrador de Organización la corrige (US-035, it. 11).
    $administrator = reportingMember($this->tenant, 'admin@veeduria-smr.org', Roles::Administrator);
    $this->actingAs($administrator, 'tenant')
        ->patchJson("http://veeduria-smr.govtrace.localhost/worksites/{$this->worksite->id}/location", ['latitude' => $realSite[0], 'longitude' => $realSite[1]])
        ->assertOk();

    sendReport($this->veedor, ['latitude' => $realSite[0], 'longitude' => $realSite[1]])->assertCreated();
});

// Técnicos --------------------------------------------------------------

it('validates the geofence with the radius in force when the report was captured, not when it arrived (R-AUD-05)', function (string $captura, bool $aceptado) {
    $this->travelTo(Carbon::parse('2026-09-27 10:00:00'));
    Parameters::set('geofence_radius_meters', '500', Carbon::parse('2026-09-01 00:00:00'));
    Parameters::set('geofence_radius_meters', '100', Carbon::parse('2026-09-27 09:00:00'));

    [$latitude, $longitude] = pointMetersNorthOf(santaMartaWorksiteLocation(), 300);

    $response = sendReport($this->veedor, [
        'latitude' => $latitude,
        'longitude' => $longitude,
        'captured_at' => Carbon::parse($captura)->toIso8601String(),
    ]);

    if ($aceptado) {
        $response->assertCreated();
        expect(storedReports($this->tenant)->sole()->geofence_radius_meters)->toBe(500);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors([
            'location' => 'Se encuentra a 0.3 km de la ubicación oficial de la obra. Para prevenir fraudes, debe acercarse a un radio de 100 metros del proyecto.',
        ]);
    }
})->with([
    'capturado con el radio de 500 m' => ['2026-09-27 08:00', true],
    'capturado con el radio de 100 m' => ['2026-09-27 09:30', false],
]);

it('accepts a report on a suspended works contract — the paralyzed works citizens most need to document', function () {
    // R-SEC-07: una obra suspendida (los "elefantes blancos") siempre admite reportes.
    reportableContract('CO1.PCCNTR.SUSPENDIDO', ['status' => 'Suspendido']);
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.SUSPENDIDO'], santaMartaWorksiteLocation());

    sendReport($this->veedor, ['secop_contract_id' => 'CO1.PCCNTR.SUSPENDIDO', 'classification' => 'Abandono'])->assertCreated();
});

it('rejects a report on a contract a veedor can no longer select, such as an annulled one', function () {
    reportableContract('CO1.PCCNTR.ANULADO', ['status' => 'cancelled']);

    sendReport($this->veedor, ['secop_contract_id' => 'CO1.PCCNTR.ANULADO'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('secop_contract_id');
});

it('only lets a Veedor de Campo create reports', function () {
    $administrator = reportingMember($this->tenant, 'admin@correo.co', Roles::Administrator);

    sendReport($administrator)->assertForbidden();

    expect(storedReports($this->tenant))->toBeEmpty();
});
