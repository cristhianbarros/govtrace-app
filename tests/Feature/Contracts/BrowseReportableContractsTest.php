<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Contracts\WorkType;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 47a — Encontrar la obra en campo (specs/PLAN.md). Traduce los
 * escenarios nuevos de features/US-016.feature y el primero de US-019 contra
 * POST /contracts/browse: al abrir "Nuevo reporte", la lista de las obras
 * reportables del municipio del veedor (por su GPS), con filtros de tipo de
 * obra, situación y entidad, plazo vencido primero, de 20 en 20, la búsqueda
 * sin tildes, y las obras cercanas encima (V18, V19). La pantalla, Vitest.
 *
 * La ubicación va en el cuerpo (POST), nunca en la URL, y no se guarda (it. 45f).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

/** Cerca de la cabecera de Santa Marta (47001); la de Ciénaga (47189) está a unos 22 km. */
const IN_SANTA_MARTA = [11.2408, -74.1990];

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Notification::fake();
    Carbon::setTestNow('2026-10-10 12:00:00');

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
});

afterEach(function () {
    Carbon::setTestNow();
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
});

/** @param  array<string, mixed>  $body */
function browse(array $body = []): TestResponse
{
    test()->flushSession();

    return test()->actingAs(test()->veedor, 'tenant')
        ->postJson('http://veeduria-smr.govtrace.localhost/contracts/browse', $body);
}

/** @return array<string, mixed> */
function fromSantaMarta(array $more = [], int $accuracy = 30): array
{
    return ['latitude' => IN_SANTA_MARTA[0], 'longitude' => IN_SANTA_MARTA[1], 'accuracy' => $accuracy, ...$more];
}

/** A reportable contract in a municipality of Magdalena. */
function contractIn(string $municipalityCode, string $object, array $overrides = []): void
{
    static $sequence = 0;
    $sequence++;

    reportableContract('CO1.PCCNTR.47'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT), [
        'object' => $object,
        'municipality_code' => $municipalityCode,
        'process_number' => "PROC-{$sequence}",
        ...$overrides,
    ]);
}

/** The six works of the order scenario, by how SECOP shows them on 2026-10-10. */
function sixWorksInSantaMarta(): void
{
    contractIn('47001', 'Parque A', ['end_date' => '2027-01-08']);              // vence en 90 días
    contractIn('47001', 'Colegio B', ['end_date' => '2026-09-30']);             // plazo vencido hace 10 días
    contractIn('47001', 'Vía C', ['end_date' => '2026-10-30']);                 // vence en 20 días
    contractIn('47001', 'Acueducto D', ['end_date' => '2026-08-31']);           // plazo vencido hace 40 días
    contractIn('47001', 'Cancha E', ['status' => 'terminado', 'end_date' => '2026-08-10']); // terminada hace 2 meses
    contractIn('47001', 'Sede F', ['end_date' => null]);                        // sin fecha de fin
}

/** @return list<string> */
function objectsOf(TestResponse $response): array
{
    return array_column($response->assertOk()->json('data'), 'object');
}

it('Al abrir Nuevo reporte veo las obras de mi municipio', function () {
    contractIn('47001', 'Pavimentación Calle 30');
    contractIn('47189', 'Muelle de Ciénaga');

    $response = browse(fromSantaMarta());

    expect($response->assertOk()->json('municipality'))->toBe(['code' => '47001', 'name' => 'Santa Marta'])
        ->and($response->json('notice'))->toBeNull()
        ->and(objectsOf($response))->toBe(['Pavimentación Calle 30'])
        ->and($response->json('municipalities'))->toBe([
            ['code' => '47189', 'name' => 'Ciénaga', 'count' => 1],
            ['code' => '47001', 'name' => 'Santa Marta', 'count' => 1],
        ]);
});

it('Puedo cambiar el municipio de la lista', function () {
    contractIn('47001', 'Pavimentación Calle 30');
    contractIn('47189', 'Muelle de Ciénaga');

    $response = browse(fromSantaMarta(['municipality' => '47189']));

    expect($response->json('municipality.name'))->toBe('Ciénaga')
        ->and(objectsOf($response))->toBe(['Muelle de Ciénaga']);
});

it('Sin municipio por el GPS, la lista abre con el primero del territorio', function (array $gps) {
    contractIn('47189', 'Muelle de Ciénaga');
    contractIn('47001', 'Pavimentación Calle 30');

    $response = browse($gps);

    expect($response->assertOk()->json('municipality.code'))->toBe('47001')
        ->and($response->json('notice'))->toBe('No pudimos saber en qué municipio está. Le mostramos las obras de Santa Marta: elija el suyo.')
        ->and(objectsOf($response))->toBe(['Pavimentación Calle 30']);
})->with([
    'negué el permiso de ubicación' => [[]],
    'una precisión de 6 km' => [fromSantaMarta(accuracy: 6000)],
    'a 45 km de la cabecera más cercana' => [['latitude' => 11.65, 'longitude' => -74.20, 'accuracy' => 20]],
]);

it('opens on the first municipality of the territory when the one asked for is not in it', function () {
    contractIn('47001', 'Pavimentación Calle 30');

    $response = browse(['municipality' => '05001']);

    expect($response->assertOk()->json('municipality.code'))->toBe('47001')
        ->and($response->json('notice'))->not->toBeNull();
});

it('Primero las de plazo vencido, luego las que vencen más pronto', function () {
    sixWorksInSantaMarta();

    expect(objectsOf(browse(fromSantaMarta())))->toBe(['Acueducto D', 'Colegio B', 'Vía C', 'Parque A', 'Sede F', 'Cancha E']);
});

it('says the situation of each work and its end date, for the screen to write it in words', function () {
    sixWorksInSantaMarta();

    $situations = collect(browse(fromSantaMarta())->json('data'))->mapWithKeys(fn (array $item) => [$item['object'] => [$item['situation'], $item['end_date']]]);

    expect($situations->all())->toBe([
        'Acueducto D' => ['overdue', '2026-08-31'],
        'Colegio B' => ['overdue', '2026-09-30'],
        'Vía C' => ['in_progress', '2026-10-30'],
        'Parque A' => ['in_progress', '2027-01-08'],
        'Sede F' => ['no_end_date', null],
        'Cancha E' => ['finished', '2026-08-10'],
    ]);
});

it('Veo 20 obras y Ver 20 más trae las siguientes', function () {
    foreach (range(1, 45) as $number) {
        contractIn('47001', sprintf('Obra %02d', $number), ['end_date' => Carbon::parse('2026-11-01')->addDays($number)->toDateString()]);
    }

    $first = browse(fromSantaMarta());
    $second = browse(fromSantaMarta(['page' => 2]));
    $third = browse(fromSantaMarta(['page' => 3]));

    expect(objectsOf($first))->toHaveCount(20)->and($first->json('has_more'))->toBeTrue()
        ->and(objectsOf($second))->toHaveCount(20)->and($second->json('has_more'))->toBeTrue()
        ->and(objectsOf($third))->toHaveCount(5)->and($third->json('has_more'))->toBeFalse()
        ->and(objectsOf($first)[0])->toBe('Obra 01')
        ->and(objectsOf($second)[0])->toBe('Obra 21');
});

it('Filtro las obras por su situación', function (string $situation, array $expected) {
    sixWorksInSantaMarta();

    expect(objectsOf(browse(fromSantaMarta(['situation' => $situation]))))->toBe($expected);
})->with([
    'Plazo vencido' => ['overdue', ['Acueducto D', 'Colegio B']],
    'En ejecución' => ['in_progress', ['Vía C', 'Parque A', 'Sede F']],
    'Terminada hace poco' => ['finished', ['Cancha E']],
    'Todas' => ['all', ['Acueducto D', 'Colegio B', 'Vía C', 'Parque A', 'Sede F', 'Cancha E']],
]);

it('Filtro las obras por su tipo', function () {
    contractIn('47001', 'Construcción del acueducto veredal');
    contractIn('47001', 'Pavimentación Calle 30');

    $response = browse(fromSantaMarta(['work_type' => WorkType::Water->value]));

    expect(objectsOf($response))->toBe(['Construcción del acueducto veredal'])
        ->and(collect($response->json('work_types'))->pluck('label')->all())->toBe([
            'Vías y puentes', 'Agua y saneamiento', 'Vivienda', 'Educación', 'Salud', 'Deporte y recreación', 'Espacio público', 'Otras',
        ]);
});

it('Filtro las obras por la entidad que contrató', function () {
    contractIn('47001', 'Pavimentación Calle 30', ['entity_name' => 'Distrito de Santa Marta']);
    contractIn('47001', 'Andenes del centro', ['entity_name' => 'Distrito de Santa Marta']);
    contractIn('47001', 'Hospital de Santa Marta', ['entity_name' => 'Gobernación del Magdalena']);

    $all = browse(fromSantaMarta());
    $filtered = browse(fromSantaMarta(['entity' => 'Gobernación del Magdalena']));

    expect($all->json('entities'))->toBe([
        ['name' => 'Distrito de Santa Marta', 'count' => 2],
        ['name' => 'Gobernación del Magdalena', 'count' => 1],
    ])->and(collect($filtered->json('data'))->pluck('entity_name')->unique()->all())->toBe(['Gobernación del Magdalena']);
});

it('La búsqueda no distingue tildes ni mayúsculas', function () {
    contractIn('47001', 'Construcción de la VÍA a Minca');
    contractIn('47001', 'Pavimentación Calle 30');

    expect(objectsOf(browse(fromSantaMarta(['q' => 'via a minca']))))->toBe(['Construcción de la VÍA a Minca']);
});

it('La búsqueda también encuentra por la entidad', function () {
    contractIn('47001', 'Escuela y espacio público El Tirol', ['entity_name' => 'Empresa de Desarrollo Urbano']);
    contractIn('47001', 'Pavimentación Calle 30');

    expect(objectsOf(browse(fromSantaMarta(['q' => 'desarrollo urbano']))))->toBe(['Escuela y espacio público El Tirol']);
});

it('searches the process number and the contractor too, and not below 3 characters', function () {
    contractIn('47001', 'Pavimentación Calle 30', ['process_number' => 'IA 846 DE 2026', 'contractor_name' => 'Consorcio Vías del Caribe']);
    contractIn('47001', 'Andenes del centro');

    expect(objectsOf(browse(fromSantaMarta(['q' => 'ia 846']))))->toBe(['Pavimentación Calle 30'])
        ->and(objectsOf(browse(fromSantaMarta(['q' => 'consorcio vias']))))->toBe(['Pavimentación Calle 30'])
        ->and(objectsOf(browse(fromSantaMarta(['q' => 'pa']))))->toHaveCount(2);
});

it('Sin resultados en mi municipio, puedo buscar en todo el territorio', function () {
    contractIn('47001', 'Pavimentación Calle 30');
    contractIn('47189', 'Muelle del puerto de Ciénaga');

    $inMunicipality = browse(fromSantaMarta(['q' => 'Muelle del puerto']));
    $everywhere = browse(fromSantaMarta(['q' => 'Muelle del puerto', 'scope' => 'territory']));

    expect(objectsOf($inMunicipality))->toBe([])
        ->and(objectsOf($everywhere))->toBe(['Muelle del puerto de Ciénaga'])
        ->and($everywhere->json('data.0.municipality'))->toBe('Ciénaga');
});

it('Una obra sin ubicación aparece con Sin ubicación todavía', function () {
    contractIn('47001', 'Pavimentación Calle 30');
    contractIn('47001', 'Andenes del centro');
    $located = DB::table('contracts')->where('object', 'Andenes del centro')->value('secop_contract_id');
    $unlocated = DB::table('contracts')->where('object', 'Pavimentación Calle 30')->value('secop_contract_id');
    worksiteWithContracts($this->tenant, [$located], pointMetersNorthOf(IN_SANTA_MARTA, 2_000));
    worksiteWithContracts($this->tenant, [$unlocated], null);

    $located = collect(browse(fromSantaMarta())->json('data'))->mapWithKeys(fn (array $item) => [$item['object'] => $item['located']]);

    expect($located->all())->toBe(['Pavimentación Calle 30' => false, 'Andenes del centro' => true]);
});

it('Las obras cercanas encabezan la lista, en Cerca de usted', function () {
    contractIn('47001', 'Obra a 80 m');
    contractIn('47001', 'Obra a 300 m');
    worksiteWithContracts($this->tenant, [DB::table('contracts')->where('object', 'Obra a 80 m')->value('secop_contract_id')], pointMetersNorthOf(IN_SANTA_MARTA, 80));
    worksiteWithContracts($this->tenant, [DB::table('contracts')->where('object', 'Obra a 300 m')->value('secop_contract_id')], pointMetersNorthOf(IN_SANTA_MARTA, 300));

    $nearby = browse(fromSantaMarta(accuracy: 15))->json('nearby');

    expect(array_column($nearby, 'distance_meters'))->toBe([80, 300])
        ->and(array_column(array_column($nearby, 'contract'), 'object'))->toBe(['Obra a 80 m', 'Obra a 300 m']);
});

it('Con una lectura de más de 50 m no se buscan obras cercanas: the server does not search with it', function (int $accuracy) {
    contractIn('47001', 'Obra a 100 m');
    worksiteWithContracts($this->tenant, [DB::table('contracts')->where('object', 'Obra a 100 m')->value('secop_contract_id')], pointMetersNorthOf(IN_SANTA_MARTA, 100));

    $response = browse(fromSantaMarta(accuracy: $accuracy));

    expect($response->json('nearby'))->toBeNull()
        ->and($response->json('municipality.code'))->toBe($accuracy <= 5000 ? '47001' : null);
})->with([120, 2000]);

it('Mi ubicación no viaja en la URL al pedir las obras de mi municipio: a GET with it is not allowed, and nothing keeps it', function () {
    contractIn('47001', 'Pavimentación Calle 30');
    $auditRows = DB::table('audit_logs')->count();

    browse(fromSantaMarta())->assertOk();

    $this->flushSession();
    $this->actingAs($this->veedor, 'tenant')
        ->getJson('http://veeduria-smr.govtrace.localhost/contracts/browse?latitude='.IN_SANTA_MARTA[0].'&longitude='.IN_SANTA_MARTA[1])
        ->assertStatus(405);

    if (tenant()) {
        tenancy()->end();
    }
    expect(DB::table('audit_logs')->count())->toBe($auditRows)
        ->and($this->tenant->run(fn () => DB::table('reports')->count()))->toBe(0);
});

it('validates what it is asked, and is only for veedores', function () {
    browse(['latitude' => 95, 'longitude' => -74.2, 'accuracy' => 10])->assertUnprocessable();
    browse(['situation' => 'cualquiera'])->assertUnprocessable();
    browse(['work_type' => 'cualquiera'])->assertUnprocessable();
    browse(['municipality' => '47001; drop table'])->assertUnprocessable();

    $administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->flushSession();
    $this->actingAs($administrator, 'tenant')->postJson('http://veeduria-smr.govtrace.localhost/contracts/browse', [])->assertForbidden();
});

it('shows the department-level contracts of a watched department as their own choice', function () {
    contractIn('47001', 'Pavimentación Calle 30');
    reportableContract('CO1.PCCNTR.4799999', ['object' => 'Hospital departamental', 'municipality_code' => null]);

    $choices = browse(fromSantaMarta())->json('municipalities');
    $department = browse(fromSantaMarta(['municipality' => 'dep:47']));

    expect(collect($choices)->firstWhere('code', 'dep:47'))->toBe(['code' => 'dep:47', 'name' => 'Magdalena: contratos de la gobernación', 'count' => 1])
        ->and(objectsOf($department))->toBe(['Hospital departamental']);
});
