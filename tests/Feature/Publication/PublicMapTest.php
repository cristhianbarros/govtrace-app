<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Roles;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 24 — Pines del mapa por color de estado (specs/PLAN.md).
 * Traduce features/US-027.feature (12 casos) contra GET /public/worksites,
 * la carga inicial del mapa público de cada organización (R-MAP-01): solo
 * [{id, lat, lng, color_pin}] (R-MAP-02). La pantalla es de la it. 26.
 *
 * El color es el peor entre el de la evidencia publicada más reciente
 * ("Retraso" amarillo, "Abandono" rojo) y el de los contratos de la ficha
 * (rojo si uno venció y SECOP lo sigue mostrando en ejecución, US-034).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

/** @return list<array<string, mixed>> the pins of the public map of veeduria-smr */
function mapPins(): array
{
    return publicGet('/public/worksites')->assertOk()->json('data');
}

function pinOf(Worksite $worksite): ?string
{
    return collect(mapPins())->firstWhere('id', $worksite->public_id)['color_pin'] ?? null;
}

/** An anchored worksite of the contract, in Santa Marta; the contract as SECOP shows it, ending months from today. */
function anchoredWorksite(string $secopContractId, string $status = 'En ejecución', int $endsInMonths = 3): Worksite
{
    reportableContract($secopContractId, ['status' => $status, 'end_date' => now()->addMonths($endsInMonths)->toDateString()]);

    return worksiteWithContracts(test()->tenant, [$secopContractId], santaMartaWorksiteLocation());
}

/** A report of veeduria-smr on the contract, captured minutes ago, sealed and published. */
function publishedOn(string $secopContractId, string $classification, int $minutesAgo): int
{
    return publishedReport(test()->tenant, test()->veedor, test()->administrator, [
        'secop_contract_id' => $secopContractId,
        'classification' => $classification,
        'captured_at' => now()->subMinutes($minutesAgo)->toIso8601String(),
    ]);
}

it('Color del pin según el estado de la obra', function (string $status, int $endsInMonths, ?string $latestPublished, string $color) {
    $worksite = anchoredWorksite('CO1.PCCNTR.1234567', $status, $endsInMonths);
    if ($latestPublished !== null) {
        publishedOn('CO1.PCCNTR.1234567', $latestPublished, 10);
    }

    expect(pinOf($worksite))->toBe($color);
})->with([
    'está en ejecución, en plazo y sin evidencias publicadas' => ['En ejecución', 3, null, 'green'],
    'está en plazo y su evidencia publicada más reciente es "Retraso"' => ['En ejecución', 3, 'Retraso', 'yellow'],
    'está en plazo y su evidencia publicada más reciente es "Abandono"' => ['En ejecución', 3, 'Abandono', 'red'],
    'venció su plazo y SECOP la sigue mostrando "En ejecución"' => ['En ejecución', -1, null, 'red'],
    // "Liquidada": en SECOP II, "Cerrado" (R-SEC-07), dentro de la ventana de 12 meses.
    'fue liquidada hace 3 meses y no tiene evidencias publicadas' => ['Cerrado', -3, null, 'green'],
    'fue liquidada hace 3 meses y su evidencia publicada más reciente es "Retraso"' => ['Cerrado', -3, 'Retraso', 'yellow'],
]);

it('El color refleja la evidencia publicada más reciente: an "Avance" after an "Abandono" takes the red away', function () {
    $worksite = anchoredWorksite('CO1.PCCNTR.1234567');
    publishedOn('CO1.PCCNTR.1234567', 'Abandono', 30);
    expect(pinOf($worksite))->toBe('red');

    publishedOn('CO1.PCCNTR.1234567', 'Avance', 10);

    expect(pinOf($worksite))->toBe('green');
});

it('Las evidencias no publicadas no cambian el color: a hidden "Abandono" leaves it green', function () {
    $worksite = anchoredWorksite('CO1.PCCNTR.1234567');
    expect(pinOf($worksite))->toBe('green');

    sealedReport($this->tenant, $this->veedor, ['classification' => 'Abandono']);

    expect(pinOf($worksite))->toBe('green');
});

it('Una ficha con varios contratos toma el peor estado: one in deadline and one overdue still in execution', function () {
    reportableContract('CO1.PCCNTR.1111111', ['end_date' => now()->addMonths(3)->toDateString()]);
    reportableContract('CO1.PCCNTR.3333333', ['end_date' => now()->subMonth()->toDateString()]);
    $gaira = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'], santaMartaWorksiteLocation());

    expect(pinOf($gaira))->toBe('red');
});

it('Las obras sin ubicación no tienen pin', function () {
    reportableContract('CO1.PCCNTR.7654321');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.7654321'], null);
    $anchored = anchoredWorksite('CO1.PCCNTR.1234567');

    expect(array_column(mapPins(), 'id'))->toBe([$anchored->public_id]);
});

it('El mapa de una organización no muestra obras de otra: not even one with published evidence', function () {
    $cienaga = (new RegisterOrganization)->handle('901234567-7', 'Veeduría Ciénaga', 'veeduria-cienaga');
    (new ConfigureTerritory)->handle($cienaga, ['47189']);
    reportableContract('CO1.PCCNTR.7777777', ['municipality_code' => '47189', 'object' => 'Muelle de Ciénaga']);
    worksiteWithContracts($cienaga, ['CO1.PCCNTR.7777777'], [11.0069, -74.2476]);
    publishedReport(
        $cienaga,
        reportingMember($cienaga, 'laura@correo.co'),
        reportingMember($cienaga, 'admin@veeduria-cienaga.org', Roles::Administrator),
        ['secop_contract_id' => 'CO1.PCCNTR.7777777', 'classification' => 'Abandono', 'latitude' => 11.0069, 'longitude' => -74.2476],
    );
    $own = anchoredWorksite('CO1.PCCNTR.1234567');

    $pins = mapPins();

    // Las dos bases numeran sus fichas desde 1: se distingue por dónde está.
    expect($pins)->toHaveCount(1)
        ->and($pins[0])->toBe(['id' => $own->public_id, 'lat' => 11.241, 'lng' => -74.199, 'color_pin' => 'green']);
});

it('La carga inicial del mapa es liviana: only id, latitude, longitude and color of each pin', function () {
    $worksite = anchoredWorksite('CO1.PCCNTR.1234567');
    publishedOn('CO1.PCCNTR.1234567', 'Retraso', 10);

    $pins = mapPins();

    expect($pins)->toHaveCount(1)
        ->and(array_keys($pins[0]))->toBe(['id', 'lat', 'lng', 'color_pin'])
        ->and($pins[0])->toMatchArray(['id' => $worksite->public_id, 'color_pin' => 'yellow']);
});

// Reglas derivadas ------------------------------------------------------

it('lets the newest evidence decide, not the last one published: an older "Abandono" published later keeps it green', function () {
    $worksite = anchoredWorksite('CO1.PCCNTR.1234567');
    publishedOn('CO1.PCCNTR.1234567', 'Avance', 10);
    publishedOn('CO1.PCCNTR.1234567', 'Abandono', 40);

    expect(pinOf($worksite))->toBe('green');
});

it('no longer colors the pin with a withdrawn evidence', function () {
    $worksite = anchoredWorksite('CO1.PCCNTR.1234567');
    $abandonment = publishedOn('CO1.PCCNTR.1234567', 'Abandono', 10);
    expect(pinOf($worksite))->toBe('red');

    editorialDecision($this->administrator, 'withdraw', $abandonment, ['reason' => 'Aparece un menor de edad identificable'])->assertOk();

    expect(pinOf($worksite))->toBe('green');
});

it('takes the worst of the evidence and the contracts: a "Retraso" on an overdue contract is red', function () {
    $worksite = anchoredWorksite('CO1.PCCNTR.1234567', 'En ejecución', -1);
    publishedOn('CO1.PCCNTR.1234567', 'Retraso', 10);

    expect(pinOf($worksite))->toBe('red');
});

it('does not take a contract ending today as overdue yet, like the nightly calculation (US-034)', function () {
    reportableContract('CO1.PCCNTR.1234567', ['end_date' => today()->toDateString()]);
    $worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());

    expect(pinOf($worksite))->toBe('green');
});

it('places the pin to about 100 m: a worksite is anchored where its first veedor stood (R-PRIV-02)', function () {
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], [11.240812, -74.199034]);

    $response = publicGet('/public/worksites')->assertOk();

    expect($response->json('data.0'))->toMatchArray(['lat' => 11.241, 'lng' => -74.199])
        ->and($response->getContent())->not->toContain('11.240812')
        ->and($response->getContent())->not->toContain('74.199034');
});

it('keeps the pin of a worksite whose contract closed beyond the window or was annulled: its evidence stays public', function () {
    $closedLongAgo = anchoredWorksite('CO1.PCCNTR.1111111', 'Cerrado', -18);
    $annulled = anchoredWorksite('CO1.PCCNTR.3333333', 'cancelled', 3);

    expect(pinOf($closedLongAgo))->toBe('green')
        ->and(pinOf($annulled))->toBe('green');
});

it('follows the criteria of US-027, not the example of its title: a suspended contract in deadline is green', function () {
    $worksite = anchoredWorksite('CO1.PCCNTR.1234567', 'Suspendido', 3);

    expect(pinOf($worksite))->toBe('green');
});
