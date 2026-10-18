<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 68 — buscar el lugar de la obra por su dirección o un sitio
 * conocido (features/US-059-LEG.feature y features/US-035.feature). GovTrace
 * le pregunta a OpenStreetMap (Nominatim) desde el servidor, con solo el texto,
 * dentro de la zona del municipio de la obra. Al tocar "Buscar" (Nominatim no
 * admite autocompletar), una búsqueda por segundo y por veeduría, y cada una se
 * guarda 30 días.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const PLACES_HOST = 'http://veeduria-smr.govtrace.localhost';
const PLACES_MINCA = 'CO1.PCCNTR.7654321';

/** What Nominatim answers (jsonv2), trimmed. */
function nominatimRows(): array
{
    return [
        ['place_id' => 1, 'lat' => '11.1427', 'lon' => '-74.1173', 'name' => 'Parque de Minca', 'display_name' => 'Parque de Minca, Minca, Santa Marta, Magdalena, Colombia'],
        ['place_id' => 2, 'lat' => '11.1431', 'lon' => '-74.1169', 'name' => 'Calle del Parque', 'display_name' => 'Calle del Parque, Minca, Santa Marta, Magdalena, 470001, Colombia'],
    ];
}

/** @return array<string, string> the query string Nominatim was asked with */
function nominatimQuery(HttpRequest $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query;
}

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    // La de "array" se pierde entre peticiones con la tenencia; la de la base, como en staging, las guarda.
    config(['cache.default' => 'database']);
    Cache::flush();
    Sleep::fake();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    reportableContract(PLACES_MINCA, ['object' => 'Construcción de la vía a Minca']);
    reportableContract('CO1.PCCNTR.1234567');
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    reportableContract('CO1.PCCNTR.4700099', ['object' => 'Mantenimiento de la red vial departamental', 'entity_name' => 'Gobernación del Magdalena', 'municipality_code' => null]);
    reportableContract('CO1.PCCNTR.0500001', ['object' => 'Construcción del parque de Belén', 'department_code' => '05', 'municipality_code' => '05001']);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    Cache::flush();
});

/** GET /public/places, as a visitor without a session. */
function findPlace(string $text, array $work = ['contract_id' => PLACES_MINCA]): TestResponse
{
    tenancy()->end();

    return test()->getJson(PLACES_HOST.'/public/places?'.http_build_query(['q' => $text, ...$work]));
}

it('Escribo dónde queda la obra y el mapa la ubica: up to 5 places of OpenStreetMap around the municipality of the work, asked with only the text', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(nominatimRows())]);

    expect(findPlace('Parque de Minca')->assertOk()->json('data'))->toBe([
        ['label' => 'Parque de Minca, Minca, Santa Marta, Magdalena', 'latitude' => 11.1427, 'longitude' => -74.1173],
        ['label' => 'Calle del Parque, Minca, Santa Marta, Magdalena, 470001', 'latitude' => 11.1431, 'longitude' => -74.1169],
    ]);

    Http::assertSent(function (HttpRequest $request) {
        $query = nominatimQuery($request);
        [$west, $north, $east, $south] = array_map(floatval(...), explode(',', $query['viewbox']));

        // La zona: la distancia de la cabecera (11.2047, -74.1998) que admite una ubicación propuesta, 30 km.
        return str_starts_with($request->url(), 'https://nominatim.openstreetmap.org/search?')
            && $query['q'] === 'Parque de Minca, Santa Marta, Magdalena'
            && $query['format'] === 'jsonv2' && $query['countrycodes'] === 'co' && $query['limit'] === '5' && $query['bounded'] === '1'
            && $query['accept-language'] === 'es'
            && abs($west + 74.47) < 0.01 && abs($east + 73.93) < 0.01 && abs($north - 11.475) < 0.01 && abs($south - 10.934) < 0.01
            && str_starts_with($request->header('User-Agent')[0], 'GovTrace ');
    });
});

it('also searches around the work of a sheet, as the Administrador correcting its location, and the department of a contract without municipality', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

    findPlace('Gaira', ['worksite_id' => $this->worksite->public_id])->assertOk();
    findPlace('Puente sobre el río Frío', ['contract_id' => 'CO1.PCCNTR.4700099'])->assertOk();

    $queries = Http::recorded()->map(fn (array $sent) => nominatimQuery($sent[0]));
    expect($queries[0]['q'])->toBe('Gaira, Santa Marta, Magdalena')
        ->and($queries[1]['q'])->toBe('Puente sobre el río Frío, Magdalena')
        ->and($queries[1])->not->toHaveKey('viewbox')
        ->and($queries[1])->not->toHaveKey('bounded');
});

it('asks OpenStreetMap once a month for the same place, and at most once a second per veeduría', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(nominatimRows())]);

    findPlace('Parque de Minca')->assertOk();
    findPlace('  parque   de MINCA ')->assertOk();
    Http::assertSentCount(1);
    Sleep::assertNeverSlept();

    // Otra búsqueda en el mismo segundo espera su turno.
    findPlace('Escuela de Minca')->assertOk();
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);

    $this->travel(31)->days();
    findPlace('Parque de Minca')->assertOk();
    Http::assertSentCount(3);
});

it('Si no se encuentra el lugar, la obra se marca en el mapa: nothing found, or a search that does not answer, and that one is not kept', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::sequence()
        ->push([])
        ->push('', 503)
        ->pushFailedConnection()
        ->push(nominatimRows())]);
    $unavailable = 'No pudimos buscar el lugar ahora. Márquelo en el mapa.';

    expect(findPlace('Hangar de Minca')->assertOk()->json('data'))->toBe([]);
    findPlace('Escuela de Minca')->assertStatus(503)->assertExactJson(['message' => $unavailable]);
    findPlace('Escuela de Minca')->assertStatus(503)->assertExactJson(['message' => $unavailable]);
    expect(findPlace('Escuela de Minca')->assertOk()->json('data'))->toHaveCount(2);
});

it('validates what it is asked: between 3 and 120 characters, about a work the veeduría watches', function () {
    Http::fake();

    findPlace('ab')->assertUnprocessable()->assertJsonValidationErrors(['q' => 'Escriba al menos 3 letras del lugar.']);
    findPlace(str_repeat('a', 121))->assertUnprocessable()->assertJsonValidationErrors(['q' => 'Escriba hasta 120 caracteres.']);
    findPlace('Parque de Belén', ['contract_id' => 'CO1.PCCNTR.0500001'])->assertUnprocessable()->assertJsonValidationErrors(['contract_id' => 'Esa obra no está entre las que vigila esta veeduría.']);
    findPlace('Parque de Minca', [])->assertUnprocessable()->assertJsonValidationErrors(['worksite_id', 'contract_id']);
    Http::assertNothingSent();
});
