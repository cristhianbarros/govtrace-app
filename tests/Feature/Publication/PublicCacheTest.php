<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Publication\PublicCache;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 56 — la caché de lo público. El mapa, la lista, los filtros, las estadísticas y la
 * vista de cada obra son iguales para todos los visitantes, y se calculaban en cada visita:
 * unos 100 ms de CPU, así que una máquina pequeña atendía unas 19 peticiones por segundo
 * (it. 50b). Ahora se guardan 60 segundos (PUBLIC_CACHE_SECONDS), por organización, y una
 * decisión editorial, una ubicación corregida o una ficha agrupada los borran al instante.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);
    // El almacén de producción (la tabla cache, con un prefijo por organización). El de arreglo de la suite
    // se perdía al cambiar de contexto (al sellar un reporte), y no probaba nada de la invalidación.
    config(['limits.public_cache_seconds' => 60, 'cache.default' => 'database']);
    app('cache')->forgetDriver('database');
    Cache::clearResolvedInstances();
    DB::table('cache')->delete();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567', ['object' => 'Pavimentación Calle 30']);
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
    DB::table('cache')->delete();
});

/** Las consultas que una petición hizo a las tablas del mapa: cero si salió de la caché. */
function queriesOfTheMap(Closure $request): int
{
    $queries = 0;
    Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$queries) {
        if (preg_match('/\b(worksites|reports|contracts|worksite_contracts|evidences)\b/', $query->sql)) {
            $queries++;
        }
    });
    $request();

    return $queries;
}

it('Lo público se calcula una vez por minuto: the same request again does not touch the tables', function (string $path) {
    publicGet($path)->assertOk();

    expect(queriesOfTheMap(fn () => publicGet($path)->assertOk()))->toBe(0);
})->with([
    'los pines del mapa' => ['/public/worksites'],
    'el mapa como lista' => ['/public/worksites/list'],
    'los filtros' => ['/public/worksites/filters'],
    'las estadísticas' => ['/public/stats'],
]);

it('answers the same thing from the cache as from the tables', function () {
    $fresh = publicGet('/public/worksites')->assertOk()->json();
    $cached = publicGet('/public/worksites')->assertOk()->json();

    expect($cached)->toBe($fresh)->and($cached['data'])->toHaveCount(1);
});

it('Cada combinación de filtros tiene su propia caché: another filter is another answer', function () {
    $all = publicGet('/public/worksites')->json('data');
    $green = publicGet('/public/worksites?status=green')->json('data');
    $red = publicGet('/public/worksites?status=red')->json('data');

    expect($all)->toHaveCount(1)
        ->and($green)->toHaveCount(1)
        ->and($red)->toBe([])
        // Y cada una sigue saliendo de la suya.
        ->and(publicGet('/public/worksites?status=red')->json('data'))->toBe([]);
});

it('La caché de una veeduría no le sirve a otra: each organization has its own', function () {
    publicGet('/public/worksites')->assertOk()->assertJsonCount(1, 'data');
    tenancy()->end();

    $cienaga = (new RegisterOrganization)->handle('901234567-7', 'Veeduría Ciénaga', 'veeduria-cienaga');
    (new ConfigureTerritory)->handle($cienaga, ['47189']);
    reportableContract('CO1.PCCNTR.7777777', ['municipality_code' => '47189', 'object' => 'Muelle de Ciénaga']);
    $muelle = worksiteWithContracts($cienaga, ['CO1.PCCNTR.7777777'], [11.0069, -74.2476]);

    $theirs = publicGet('/public/worksites', 'veeduria-cienaga.govtrace.localhost')->assertOk()->json('data');

    expect(array_column($theirs, 'id'))->toBe([$muelle->public_id])
        // Y la de Santa Marta sigue siendo la suya.
        ->and(array_column(publicGet('/public/worksites')->json('data'), 'id'))->toBe([$this->worksite->public_id]);
});

it('Una publicación se ve de inmediato: the evidence appears in the worksite and its pin changes, within the minute', function () {
    $before = publicGet("/public/worksites/{$this->worksite->public_id}")->assertOk()->json('data');
    expect($before['timeline'])->toBe([])
        ->and(publicGet('/public/worksites')->json('data.0.color_pin'))->toBe('green');

    publishedReport($this->tenant, $this->veedor, $this->administrator, ['classification' => 'Abandono']);

    expect(publicGet("/public/worksites/{$this->worksite->public_id}")->json('data.timeline'))->toHaveCount(1)
        ->and(publicGet('/public/worksites')->json('data.0.color_pin'))->toBe('red')
        ->and(publicGet('/public/stats')->assertOk()->json())->not->toBe(null);
});

it('Retirar una evidencia también se ve de inmediato', function () {
    $reportId = publishedReport($this->tenant, $this->veedor, $this->administrator, ['classification' => 'Abandono']);
    expect(publicGet('/public/worksites')->json('data.0.color_pin'))->toBe('red');

    editorialDecision($this->administrator, 'withdraw', $reportId, ['reason' => 'No corresponde a la obra'])->assertOk();
    tenancy()->end();

    expect(publicGet('/public/worksites')->json('data.0.color_pin'))->toBe('green');
});

it('Corregir la ubicación de una obra se ve de inmediato en el mapa', function () {
    $latitude = publicGet('/public/worksites')->json('data.0.lat');

    test()->flushSession();
    $this->actingAs($this->administrator, 'tenant')
        ->patchJson("http://veeduria-smr.govtrace.localhost/worksites/{$this->worksite->public_id}/location", ['latitude' => 11.3, 'longitude' => -74.1])
        ->assertOk();
    tenancy()->end();

    expect(publicGet('/public/worksites')->json('data.0.lat'))->not->toBe($latitude)
        ->and(publicGet('/public/worksites')->json('data.0.lat'))->toBe(11.3);
});

it('A change nobody told it about shows up after the minute, not before', function () {
    publicGet('/public/worksites')->assertOk()->assertJsonCount(1, 'data');
    // Sin pasar por una decisión ni una corrección: un cambio directo en la base (la sincronización de SECOP, por ejemplo).
    $this->tenant->run(fn () => DB::table('worksites')->delete());
    DB::table('worksite_contracts')->where('worksite_id', $this->worksite->id)->delete();

    publicGet('/public/worksites')->assertJsonCount(1, 'data');

    $this->travel(61)->seconds();
    publicGet('/public/worksites')->assertJsonCount(0, 'data');
});

it('with PUBLIC_CACHE_SECONDS=0 nothing is kept: every request is calculated', function () {
    config(['limits.public_cache_seconds' => 0]);
    publicGet('/public/worksites')->assertOk();

    expect(queriesOfTheMap(fn () => publicGet('/public/worksites')->assertOk()))->toBeGreaterThan(0);
});

it('has a version that moves when something is published: the old answers are no longer asked for', function () {
    $this->tenant->run(function () {
        $first = PublicCache::version();
        PublicCache::forget();

        expect(PublicCache::version())->toBe($first + 1);
    });
});

it('Lo público no abre una sesión ni pone cookies: a visitor who only looks at the map costs no session row', function (string $path) {
    $response = publicGet($path)->assertOk();

    // Cada visitante anónimo abría una sesión (una fila y dos cookies) por petición, y no la usa.
    $response->assertCookieMissing('govtrace-session')->assertCookieMissing('XSRF-TOKEN');
    expect($response->headers->getCookies())->toBe([]);
})->with([
    'los pines del mapa' => ['/public/worksites'],
    'el mapa como lista' => ['/public/worksites/list'],
    'los filtros' => ['/public/worksites/filters'],
    'las estadísticas' => ['/public/stats'],
]);

it('lo que sí necesita una sesión la sigue abriendo: the login page', function () {
    publicGet('/login')->assertOk()->assertCookie('govtrace-session');
});
