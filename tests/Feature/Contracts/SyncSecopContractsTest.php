<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\ReactivateOrganization;
use App\Application\Organization\RegisterOrganization;
use App\Application\Organization\SuspendOrganization;
use App\Domain\Contracts\Contract;
use App\Domain\Contracts\SecopSyncRun;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\SyncSecopContracts;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
 * Iteración 7 — Sincronización SECOP II (specs/PLAN.md). Traduce el
 * resto de features/US-013.feature (el emparejamiento fila a fila y el
 * contrato no editable están en ProcessSecopContractRowTest). SECOP
 * responde con las fixtures grabadas de tests/fixtures/secop (R-TST-02):
 * filas reales de obra de Magdalena y Antioquia.
 *
 * Sin RefreshDatabase — crear organizaciones ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();

    // Tablas centrales que ningún tenant arrastra al borrarse. Query
    // builder a propósito: el modelo Contract prohíbe delete() (R-SEC-01).
    DB::table('contracts')->delete();
    DB::table('secop_sync_runs')->delete();
});

/** The SoQL "$where" SECOP received, uppercased for easy matching. */
function secopWhere(Request $request): string
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return mb_strtoupper((string) ($query['$where'] ?? ''));
}

/**
 * Serves the recorded fixture of whichever department the request asks
 * for. $extraMagdalenaRows añade filas sueltas (no grabadas de la API) a
 * la respuesta de Magdalena, para un caso puntual que el corpus real no
 * tiene — hoy, ningún nombre de ciudad de Magdalena queda sin emparejar.
 *
 * @param  list<array<string, mixed>>  $extraMagdalenaRows
 */
function fakeSecopApi(array $extraMagdalenaRows = []): void
{
    Http::fake(function (Request $request) use ($extraMagdalenaRows) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        if ((int) ($query['$offset'] ?? 0) > 0) {
            return Http::response([]); // cada fixture cabe en una página
        }

        return Http::response(match (true) {
            str_contains(secopWhere($request), "'MAGDALENA'") => [...secopFixture('magdalena_obras'), ...$extraMagdalenaRows],
            str_contains(secopWhere($request), "'ANTIOQUIA'") => secopFixture('antioquia_obras'),
            default => [],
        });
    });
}

function runSecopSync(?string $organizationId = null): void
{
    app()->call([new SyncSecopContracts($organizationId), 'handle']);
}

/**
 * Antecedentes de features/US-013.feature. Bogotá (11001): nadie la vigila.
 *
 * @return array{0: Tenant, 1: Tenant}
 */
function registerUs013Organizations(): array
{
    $santaMarta = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($santaMarta, ['47']); // Magdalena

    $paisa = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Paisa', 'veeduria-paisa');
    (new ConfigureTerritory)->handle($paisa, ['05001']); // Medellín

    return [$santaMarta, $paisa];
}

it('Corrida nocturna por los territorios configurados: the nightly run queries SECOP only for the configured territories and stores each contract once', function () {
    fakeSecopApi();
    registerUs013Organizations();

    runSecopSync();

    // Una consulta por departamento: Magdalena entero, y Antioquia por
    // Medellín — el municipio se empareja de este lado (ver SecopClient).
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $r) => str_contains(secopWhere($r), "'MAGDALENA'"));
    Http::assertSent(fn (Request $r) => str_contains(secopWhere($r), "'ANTIOQUIA'"));

    // Magdalena: las 6 filas se guardan — la de ciudad "No Definido" es
    // un contrato departamental (Gobernación, sin municipio propio;
    // US-015/US-016, it. 8) y Magdalena entero está vigilado.
    // Antioquia: solo las 2 de Medellín; Envigado, Peñol y Santafé de
    // Antioquia no los vigila nadie (nadie vigila el departamento entero).
    expect(Contract::query()->where('department_code', '47')->count())->toBe(6)
        ->and(Contract::query()->where('department_code', '47')->whereNull('municipality_code')->count())->toBe(1)
        ->and(Contract::query()->where('department_code', '05')->pluck('municipality_code')->unique()->values()->all())->toBe(['05001'])
        ->and(Contract::query()->count())->toBe(8);

    runSecopSync(); // la noche siguiente SECOP devuelve lo mismo

    $runs = SecopSyncRun::query()->orderBy('id')->get();

    expect(Contract::query()->count())->toBe(8)
        ->and($runs->pluck('status')->all())->toBe(['success', 'success'])
        ->and($runs[0]->only('contracts_inserted', 'contracts_updated'))->toBe(['contracts_inserted' => 8, 'contracts_updated' => 0])
        ->and($runs[1]->only('contracts_inserted', 'contracts_updated'))->toBe(['contracts_inserted' => 0, 'contracts_updated' => 8]);
});

it('No se consultan territorios sin organizaciones activas: never queries or stores contracts for a territory with no active organization', function () {
    fakeSecopApi();
    registerUs013Organizations();

    runSecopSync();

    Http::assertNotSent(fn (Request $r) => str_contains(secopWhere($r), 'BOGOT'));
    expect(Contract::query()->where('department_code', '11')->exists())->toBeFalse();
});

it('Un territorio que se queda sin organizaciones deja de actualizarse: a territory left without active organizations stops being synced, keeping what was already stored', function () {
    fakeSecopApi();
    [, $paisa] = registerUs013Organizations();

    // 12 contratos de Medellín guardados por una sincronización anterior.
    $this->travelTo($lastSync = now()->subDay()->startOfSecond());
    for ($i = 1; $i <= 12; $i++) {
        Contract::fromSecop(fn () => Contract::create([
            'secop_contract_id' => "CO1.PCCNTR.900000{$i}",
            'entity_name' => 'Alcaldía de Medellín',
            'contract_type' => 'Obra',
            'status' => 'En ejecución',
            'department_code' => '05',
            'municipality_code' => '05001',
        ]));
    }
    $this->travelBack();

    $paisa->update(['status' => 'suspended']); // era la única que vigilaba Medellín

    runSecopSync();

    $medellin = Contract::query()->where('municipality_code', '05001')->get();

    Http::assertNotSent(fn (Request $r) => str_contains(secopWhere($r), "'ANTIOQUIA'"));
    expect($medellin)->toHaveCount(12)
        ->and($medellin->every(fn (Contract $c) => $c->updated_at->equalTo($lastSync)))->toBeTrue();
});

it('discards a contract whose municipality does not match DIVIPOLA and reports it in the sync run', function () {
    // El corpus real grabado (magdalena_obras.json) ya no tiene ningún
    // nombre sin emparejar: "No Definido" ahora es un contrato
    // departamental de la Gobernación (US-015/US-016, it. 8). Esta fila
    // no viene de la API — es el Esquema del escenario de
    // features/US-013.feature ("Villa Inexistente" se descarta).
    fakeSecopApi(extraMagdalenaRows: [array_merge(secopFixture('magdalena_obras')[0], [
        'id_contrato' => 'CO1.PCCNTR.INEXISTENTE',
        'ciudad' => 'Villa Inexistente',
    ])]);
    registerUs013Organizations();

    runSecopSync();

    $run = SecopSyncRun::query()->latest('id')->first();

    expect($run->contracts_discarded)->toBe(1)
        ->and($run->unmatched_locations)->toBe(['Magdalena / Villa Inexistente' => 1])
        ->and(Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.INEXISTENTE')->exists())->toBeFalse();
});

it('Falla de la API de SECOP II: records the failure and lets the queue retry with exponential backoff', function () {
    Http::fake(['www.datos.gov.co/*' => Http::response('', 504)]);
    registerUs013Organizations();

    $job = new SyncSecopContracts;

    expect(fn () => app()->call([$job, 'handle']))->toThrow(RequestException::class);

    $run = SecopSyncRun::query()->latest('id')->first();

    expect($run->status)->toBe('failed')
        ->and($run->error_message)->toContain('504')
        ->and($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([60, 300, 900, 3600]);
});

it('schedules the nightly sync at 02:00 by default', function () {
    Artisan::call('schedule:list', ['--timezone' => 'America/Bogota']); // it. 45a: la hora de Colombia

    expect(Artisan::output())->toMatch('/0\s+2\s+\*\s+\*\s+\*\s+secop-sync-nightly/');
});

// Esquema "Sincronización inmediata al activar una organización o cambiar
// su territorio" — una fila por test.

it('Sincronización inmediata al activar una organización o cambiar su territorio: queues an immediate sync when the Super Administrator registers an organization', function () {
    Queue::fake();

    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    Queue::assertPushed(SyncSecopContracts::class, fn (SyncSecopContracts $job) => $job->organizationId === $tenant->id);
});

it('Sincronización inmediata al activar una organización o cambiar su territorio: queues an immediate sync when the Super Administrator reactivates a suspended organization', function () {
    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new SuspendOrganization)->handle($tenant);
    Queue::fake();

    (new ReactivateOrganization)->handle($tenant);

    Queue::assertPushed(SyncSecopContracts::class, fn (SyncSecopContracts $job) => $job->organizationId === $tenant->id);
});

it('Sincronización inmediata al activar una organización o cambiar su territorio: queues an immediate sync when an Organization Administrator changes the territory', function () {
    Queue::fake();

    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($tenant, ['47001']);

    Queue::assertPushed(SyncSecopContracts::class, 2);
    Queue::assertPushed(SyncSecopContracts::class, fn (SyncSecopContracts $job) => $job->organizationId === $tenant->id);
});

it('an immediate sync only queries the territory of the organization that triggered it', function () {
    fakeSecopApi();
    [$santaMarta] = registerUs013Organizations();

    runSecopSync($santaMarta->id);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => str_contains(secopWhere($r), "'MAGDALENA'"));
    expect(SecopSyncRun::query()->latest('id')->first()->organization_id)->toBe($santaMarta->id);
});
