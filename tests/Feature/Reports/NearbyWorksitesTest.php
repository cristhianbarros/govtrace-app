<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Configuration\Parameters;
use App\Domain\Configuration\ParameterValue;
use App\Domain\Organization\Roles;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 31 — Sugerencia de obras cercanas (specs/PLAN.md). Traduce
 * features/US-019.feature (7 casos) contra POST /worksites/nearby: hasta 5
 * obras ancladas a menos de 500 m (la geocerca, US-038-CFG), de la más
 * cercana a la más lejana, con Haversine en SQL (D10). Las mismas reglas de
 * US-016: del territorio (R-VC-04) y reportables. La pantalla, Vitest.
 *
 * It. 45f: la ubicación va en el cuerpo (POST), nunca en la URL: las URL
 * quedan en los registros de acceso del proxy y del servidor web.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const ME = [11.2408, -74.1990];

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Notification::fake();
    $this->lastParameterVersion = (int) ParameterValue::query()->max('id');

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    ParameterValue::query()->where('id', '>', $this->lastParameterVersion)->delete();
});

/** An anchored worksite $meters north of me, with its own contract as SECOP shows it. */
function worksiteAt(int $meters, array $contract = [], bool $anchored = true): Worksite
{
    $secopContractId = 'CO1.PCCNTR.'.str_pad((string) $meters, 7, '0', STR_PAD_LEFT);
    reportableContract($secopContractId, ['object' => "Obra a {$meters} m", ...$contract]);

    return worksiteWithContracts(test()->tenant, [$secopContractId], $anchored ? pointMetersNorthOf(ME, $meters) : null);
}

function nearby(array $position = ME): TestResponse
{
    test()->flushSession();

    return test()->actingAs(test()->veedor, 'tenant')
        ->postJson('http://veeduria-smr.govtrace.localhost/worksites/nearby', ['latitude' => $position[0], 'longitude' => $position[1]]);
}

it('Hasta 5 obras dentro de 500 m ordenadas por distancia', function () {
    foreach ([420, 50, 650, 200, 480, 120, 310] as $meters) {
        worksiteAt($meters);
    }

    $suggested = nearby()->assertOk()->json('data');

    expect(array_column($suggested, 'distance_meters'))->toBe([50, 120, 200, 310, 420])
        ->and($suggested[0])->toMatchArray(['name' => 'Obra a 50 m'])
        ->and($suggested[0]['contract'])->toMatchArray(['secop_contract_id' => 'CO1.PCCNTR.0000050', 'object' => 'Obra a 50 m', 'entity_name' => 'Alcaldía Distrital de Santa Marta']);
});

it('Solo se sugieren obras seleccionables de mi territorio y ancladas', function (array $contract, bool $anchored, bool $suggested) {
    worksiteAt(100, $contract, $anchored);

    expect(nearby()->assertOk()->json('data'))->toHaveCount($suggested ? 1 : 0);
})->with([
    'en ejecución y anclada' => [[], true, true],
    'liquidada hace 13 meses' => [['status' => 'Cerrado', 'end_date' => now()->subMonths(13)->toDateString()], true, false],
    'anulada' => [['status' => 'cancelled'], true, false],
    'sin ubicación oficial' => [[], false, false],
    'de otro territorio no vigilado' => [['department_code' => '05', 'municipality_code' => '05001'], true, false],
]);

it('Ninguna obra cercana: an empty list, and the screen says so', function () {
    worksiteAt(650);

    expect(nearby()->assertOk()->json('data'))->toBe([]);
});

// Reglas derivadas ------------------------------------------------------

it('measures the real distance, not a square: a worksite 400 m north and 400 m east is 566 m away', function () {
    reportableContract('CO1.PCCNTR.0000566', ['object' => 'Obra en la esquina']);
    $north = pointMetersNorthOf(ME, 400)[0];
    $east = ME[1] + rad2deg(400 / (6_371_000 * cos(deg2rad(ME[0]))));
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.0000566'], [$north, $east]);

    expect(nearby()->assertOk()->json('data'))->toBe([]);
});

it('measures from where the veedor is, and follows the geofence set by the Super Administrador', function () {
    worksiteAt(600);
    // Vigente desde justo después de la versión de hoy (la migración la fechó al correr).
    $latest = Carbon::parse(ParameterValue::query()->where('key', 'geofence_radius_meters')->max('effective_from'));
    Parameters::set('geofence_radius_meters', '700', $latest->addSecond());

    expect(array_column(nearby()->assertOk()->json('data'), 'distance_meters'))->toBe([600]);
});

it('needs a valid position, and is only for veedores', function () {
    nearby([95, -74.199])->assertUnprocessable();

    $administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->flushSession();
    $this->actingAs($administrator, 'tenant')->postJson('http://veeduria-smr.govtrace.localhost/worksites/nearby', ['latitude' => 11.24, 'longitude' => -74.19])->assertForbidden();
});

it('Mi ubicación no viaja en la URL de la petición: it takes the location in the body, and a GET with it in the URL is not allowed', function () {
    worksiteAt(50);

    expect(array_column(nearby()->assertOk()->json('data'), 'distance_meters'))->toBe([50]);

    $this->flushSession();
    $this->actingAs($this->veedor, 'tenant')
        ->getJson('http://veeduria-smr.govtrace.localhost/worksites/nearby?latitude='.ME[0].'&longitude='.ME[1])
        ->assertStatus(405);
});

it('says in Spanish that the address is only for the app, when it is opened in the browser (it. 46f)', function () {
    $message = 'Esta dirección no se abre en el navegador: la usa la app por dentro. Vuelva a la pantalla anterior.';
    $this->flushSession();

    $this->actingAs($this->veedor, 'tenant')
        ->getJson('http://veeduria-smr.govtrace.localhost/worksites/nearby')
        ->assertStatus(405)
        ->assertExactJson(['message' => $message]);

    $this->withoutVite()->actingAs($this->veedor, 'tenant')
        ->get('http://veeduria-smr.govtrace.localhost/worksites/nearby')
        ->assertStatus(405)
        ->assertSee($message)
        ->assertDontSee('The GET method is not supported');
});
