<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Contracts\SecopSyncRun;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\SyncSecopContracts;
use App\Models\User as SuperAdmin;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 22 — Panel de salud de la sincronización SECOP II
 * (specs/PLAN.md). Traduce features/US-014.feature (3 casos) contra el
 * panel global (GET /admin/secop-health/data), y comprueba que la
 * sincronización guarda lo que el panel muestra: el desglose por
 * organización, el error de SECOP en palabras y cuándo es el reintento.
 *
 * Las horas se muestran en la hora de Colombia.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    DB::table('secop_sync_runs')->delete();

    $this->smr = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->smr, ['47']); // todo Magdalena
    $this->cienaga = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');
    (new ConfigureTerritory)->handle($this->cienaga, ['47189']); // solo el municipio de Ciénaga
    $this->superAdmin = SuperAdmin::factory()->create();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('secop_sync_runs')->delete();
    $this->superAdmin->delete();
});

function secopHealth(): ?array
{
    return test()->actingAs(test()->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/secop-health/data')->assertOk()->json('data');
}

/** That hour in Colombia, as the instant the job stores (UTC): Eloquent doesn't convert zones. */
function bogota(string $time): Carbon
{
    return Carbon::parse("2026-09-28 {$time}", 'America/Bogota')->utc();
}

it('Métricas de la última ejecución exitosa: start, end, status, processed per organization and discarded', function () {
    SecopSyncRun::create([
        'started_at' => bogota('02:00'),
        'finished_at' => bogota('02:07'),
        'status' => 'success',
        'contracts_inserted' => 20,
        'contracts_updated' => 130,
        'contracts_discarded' => 3,
        'unmatched_locations' => ['Magdalena / Villa Inexistente' => 3],
        'per_organization' => [$this->smr->id => ['inserted' => 20, 'updated' => 130], $this->cienaga->id => ['inserted' => 2, 'updated' => 5]],
    ]);

    expect(secopHealth())->toBe([
        'status' => 'Success',
        'healthy' => true,
        'date' => '28/09/2026',
        'started_at' => '02:00',
        'finished_at' => '02:07',
        'processed' => 150,
        'inserted' => 20,
        'updated' => 130,
        'discarded' => 3,
        'unmatched_locations' => ['Magdalena / Villa Inexistente' => 3],
        'per_organization' => [
            ['organization' => 'Veeduría Ciénaga', 'inserted' => 2, 'updated' => 5],
            ['organization' => 'Veeduría Ciudadana Santa Marta', 'inserted' => 20, 'updated' => 130],
        ],
        'message' => null,
    ]);
});

it('La última sincronización falló por timeout: red indicator and the message with the retry', function () {
    SecopSyncRun::create([
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
        'status' => 'failed',
        'error_message' => 'HTTP 504 Gateway Timeout',
        'next_retry_at' => now()->addMinutes(30),
    ]);

    $health = secopHealth();

    expect($health['healthy'])->toBeFalse()
        ->and($health['status'])->toBe('Failed')
        ->and($health['message'])->toBe('Falla de sincronización con SECOP II: El servicio remoto no respondió (Error HTTP 504 Gateway Timeout). Reintento programado en 30 minutos.');
});

it('Un Administrador de Organización no accede al panel de salud', function () {
    $administrator = reportingMember($this->smr, 'ana.perez@veeduria-smr.org', Roles::Administrator);

    $this->actingAs($administrator, 'tenant')->getJson('http://govtrace.localhost/admin/secop-health/data')->assertUnauthorized();
    $this->actingAs($administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/admin/secop-health/data')->assertNotFound();
});

// Lo que la sincronización guarda para el panel ---------------------------

it('records how many contracts each organization got, according to its own territory', function () {
    $magdalena = secopFixture('magdalena_obras');
    Http::fake(function ($request) use ($magdalena) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return Http::response((int) ($query['$offset'] ?? 0) === 0 && str_contains(mb_strtoupper($query['$where'] ?? ''), "'MAGDALENA'") ? $magdalena : []);
    });

    app()->call([new SyncSecopContracts, 'handle']);

    $run = SecopSyncRun::query()->sole();
    $inCienaga = count(array_filter($magdalena, fn (array $row) => $row['ciudad'] === 'Ciénaga'));

    expect($run->status)->toBe('success')
        ->and($run->per_organization[$this->smr->id])->toBe(['inserted' => count($magdalena), 'updated' => 0])
        ->and($run->per_organization[$this->cienaga->id])->toBe(['inserted' => $inCienaga, 'updated' => 0]);
});

it('records the SECOP error in words and when the queue retries it', function () {
    Http::fake(['www.datos.gov.co/*' => Http::response('', 504)]);

    expect(fn () => app()->call([new SyncSecopContracts, 'handle']))->toThrow(Exception::class);

    $run = SecopSyncRun::query()->sole();
    expect($run->error_message)->toBe('HTTP 504 Gateway Timeout')
        // Primer intento fallido: la cola reintenta al minuto (backoff 60, 300, 900, 3600).
        ->and(abs(now()->addMinute()->diffInSeconds($run->next_retry_at)))->toBeLessThan(5);
});

it('says so when no sync has run yet, and serves the screen', function () {
    expect(secopHealth())->toBeNull();

    $this->withoutVite()->actingAs($this->superAdmin, 'web')->get('http://govtrace.localhost/admin/secop-health')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/SecopHealth'));
});
