<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Contracts\SecopSyncRun;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\SyncSecopContracts;
use Carbon\CarbonImmutable;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 63 — sincronizar el SECOP desde la veeduría (features/US-070.feature).
 * El Administrador pide la sincronización de su territorio sin esperar a la
 * noche: el mismo trabajo, con el id de su veeduría, una vez cada 5 minutos por
 * veeduría. La pantalla dice cómo le fue a la última que cubrió su territorio.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const SYNC_HOST = 'http://veeduria-smr.govtrace.localhost';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    DB::table('secop_sync_runs')->delete();
    // La caché de "array" se puede etiquetar, y la tenencia la rehace en cada petición: la pausa de 5 minutos
    // se perdería entre una y otra. La de la base, como en staging: la conserva, con el prefijo de cada veeduría.
    config(['cache.default' => 'database']);
    Cache::flush();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    Queue::fake(); // lo que despacharon el registro y el territorio no cuenta
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
    DB::table('secop_sync_runs')->delete();
    DB::table('audit_logs')->delete();
    Cache::flush();
});

function syncNow($member = null, string $host = SYNC_HOST): TestResponse
{
    tenancy()->end();
    test()->flushSession();

    return test()->actingAs($member ?? test()->administrator, 'tenant')->postJson("{$host}/contracts/sync");
}

function lastSync($member = null): TestResponse
{
    tenancy()->end();
    test()->flushSession();

    return test()->actingAs($member ?? test()->administrator, 'tenant')->getJson(SYNC_HOST.'/contracts/sync');
}

it('La veeduría pide la sincronización de su territorio sin esperar a la noche: only its own territory', function () {
    syncNow()->assertStatus(202)->assertJson(['message' => 'Buscamos los contratos de su territorio en el SECOP II. En unos minutos verá el resultado aquí.']);

    Queue::assertPushed(SyncSecopContracts::class, 1);
    Queue::assertPushed(SyncSecopContracts::class, fn (SyncSecopContracts $job) => $job->organizationId === $this->tenant->id);
});

it('Una sincronización cada 5 minutos por veeduría: another veeduría may ask for its own, and after 5 minutes it may again', function () {
    syncNow()->assertStatus(202);

    $this->travel(2)->minutes();
    syncNow()->assertStatus(429)->assertJson(['message' => 'Ya pidió una sincronización hace menos de 5 minutos. Espere su resultado.']);

    tenancy()->end();
    $other = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciudadana Ciénaga', 'veeduria-cng');
    (new ConfigureTerritory)->handle($other, ['47']);
    $otherAdministrator = reportingMember($other, 'admin@veeduria-cng.org', Roles::Administrator);
    syncNow($otherAdministrator, 'http://veeduria-cng.govtrace.localhost')->assertStatus(202);

    $this->travel(4)->minutes();
    syncNow()->assertStatus(202);
});

it('La pantalla dice cómo le fue a la última sincronización: the nightly one or its own, never another veeduría\'s', function () {
    expect(lastSync()->assertOk()->json('data'))->toBeNull();

    SecopSyncRun::query()->create([
        'organization_id' => null, 'status' => 'success',
        'started_at' => CarbonImmutable::parse('2026-10-17 08:30', 'America/Bogota')->utc(), 'finished_at' => CarbonImmutable::parse('2026-10-17 08:41', 'America/Bogota')->utc(),
        'contracts_inserted' => 40, 'contracts_updated' => 300, 'contracts_discarded' => 0,
        'per_organization' => [$this->tenant->id => ['inserted' => 3, 'updated' => 12], 'otra' => ['inserted' => 37, 'updated' => 288]],
    ]);
    expect(lastSync()->json('data'))->toMatchArray(['status' => 'success', 'inserted' => 3, 'updated' => 12, 'finished_at' => '2026-10-17T13:41:00+00:00']);

    // La de otra veeduría no es la suya.
    SecopSyncRun::query()->create(['organization_id' => 'otra-veeduria', 'status' => 'running', 'started_at' => now(), 'contracts_inserted' => 0, 'contracts_updated' => 0, 'contracts_discarded' => 0]);
    expect(lastSync()->json('data.status'))->toBe('success');

    // La suya, en curso.
    SecopSyncRun::query()->create(['organization_id' => $this->tenant->id, 'status' => 'running', 'started_at' => now(), 'contracts_inserted' => 0, 'contracts_updated' => 0, 'contracts_discarded' => 0]);
    expect(lastSync()->json('data'))->toMatchArray(['status' => 'running', 'inserted' => 0, 'updated' => 0, 'finished_at' => null]);
});

it('Solo el Administrador sincroniza su veeduría', function () {
    syncNow($this->veedor)->assertForbidden();
    lastSync($this->veedor)->assertForbidden();
    Queue::assertNotPushed(SyncSecopContracts::class);
});
