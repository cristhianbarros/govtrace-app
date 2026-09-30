<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * It. 40b — la vista de una obra dice su estado y por qué (US-029, con las
 * reglas de color de US-027). Hasta aquí, el color solo se veía en el pin
 * del mapa, y nada explicaba el rojo o el amarillo (docs/ux-analisis.md).
 * El estado sale de las mismas reglas que el pin (PinColor), así que la
 * obra y su pin nunca dicen cosas distintas.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'admin@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->where('action', 'like', 'evidence.%')->delete();
});

/** @return array{color: string, label: string, reason: string} what the view of the worksite says of it */
function conditionOfWorksite(int $worksiteId): array
{
    return publicGet("/public/worksites/{$worksiteId}")->assertOk()->json('data.condition');
}

function worksiteOnTime(Tenant $tenant): int
{
    reportableContract('CO1.PCCNTR.1234567');

    return worksiteWithContracts($tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation())->id;
}

/** A report published by the Administrador, captured two days ago; its capture day in Colombia. */
function publishedTwoDaysAgo(object $test, string $classification): string
{
    $capturedAt = CarbonImmutable::now()->subDays(2)->startOfHour();
    publishedReport($test->tenant, $test->veedor, $test->administrator, ['classification' => $classification, 'captured_at' => $capturedAt->toIso8601String()]);

    return $capturedAt->setTimezone('America/Bogota')->format('d/m/Y');
}

it('La obra muestra su estado y por qué: normal, when no published report speaks of a delay or an abandonment and the contract is on time', function () {
    $worksiteId = worksiteOnTime($this->tenant);

    expect(conditionOfWorksite($worksiteId))->toBe([
        'color' => 'green',
        'label' => 'Normal',
        'reason' => 'Ningún reporte publicado habla de retraso o abandono, y el contrato está dentro del plazo.',
    ]);
});

it('La obra muestra su estado y por qué: normal, with the date of the latest published report of progress', function () {
    $worksiteId = worksiteOnTime($this->tenant);
    $day = publishedTwoDaysAgo($this, 'Avance');

    expect(conditionOfWorksite($worksiteId))->toBe([
        'color' => 'green',
        'label' => 'Normal',
        'reason' => "El reporte publicado más reciente, del {$day}, es de avance.",
    ]);
});

it('La obra muestra su estado y por qué: an alert when the latest published report is of a delay', function () {
    $worksiteId = worksiteOnTime($this->tenant);
    $day = publishedTwoDaysAgo($this, 'Retraso');

    expect(conditionOfWorksite($worksiteId))->toBe([
        'color' => 'yellow',
        'label' => 'Alerta',
        'reason' => "El reporte publicado más reciente, del {$day}, es de retraso.",
    ]);
});

it('La obra muestra su estado y por qué: at risk when the latest published report is of an abandonment', function () {
    $worksiteId = worksiteOnTime($this->tenant);
    $day = publishedTwoDaysAgo($this, 'Abandono');

    expect(conditionOfWorksite($worksiteId))->toBe([
        'color' => 'red',
        'label' => 'En riesgo',
        'reason' => "El reporte publicado más reciente, del {$day}, es de abandono.",
    ]);
});

it('La obra muestra su estado y por qué: at risk when its end date passed and SECOP still shows it in execution, like its pin', function () {
    reportableContract('CO1.PCCNTR.1234567', ['end_date' => CarbonImmutable::today()->subMonth()->toDateString()]);
    $worksiteId = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation())->id;

    expect(conditionOfWorksite($worksiteId))->toBe([
        'color' => 'red',
        'label' => 'En riesgo',
        'reason' => 'La fecha de terminación del contrato ya pasó y SECOP II lo sigue mostrando en ejecución.',
    ])
        ->and(publicGet('/public/worksites')->json('data.0.color_pin'))->toBe('red');
});

it('names every reason when there is more than one', function () {
    reportableContract('CO1.PCCNTR.1234567');
    reportableContract('CO1.PCCNTR.7654321', ['end_date' => CarbonImmutable::today()->subMonth()->toDateString()]);
    $worksiteId = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567', 'CO1.PCCNTR.7654321'], santaMartaWorksiteLocation())->id;
    $day = publishedTwoDaysAgo($this, 'Retraso');

    expect(conditionOfWorksite($worksiteId))->toBe([
        'color' => 'red',
        'label' => 'En riesgo',
        'reason' => "La fecha de terminación del contrato ya pasó y SECOP II lo sigue mostrando en ejecución. El reporte publicado más reciente, del {$day}, es de retraso.",
    ]);
});
