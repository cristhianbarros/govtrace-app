<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests extend Tests\TestCase (full Laravel app). Add
| Illuminate\Foundation\Testing\RefreshDatabase per test file when
| a scenario touches the database.
|
*/

pest()->extend(TestCase::class)
    ->beforeEach(function () {
        // QUEUE_CONNECTION=sync en testing (phpunit.xml) ejecuta cualquier
        // Job de inmediato, en el mismo test. Sin esto, cualquier test que
        // toque RegisterOrganization/ConfigureTerritory (it. 7 en adelante
        // los usa para disparar SyncSecopContracts) golpearía la API real
        // de SECOP. Un test que sí necesite correr el Job lo hace explícito
        // llamando dispatchSync() o instanciándolo directamente.
        Queue::fake();

        // R-TST-02: `make test` nunca llama a la API real de SECOP II (ni a
        // ningún otro servicio vía el cliente HTTP de Laravel). Un test que
        // olvide su Http::fake() falla en vez de salir a internet.
        Http::preventStrayRequests();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * A recorded SODA response from tests/fixtures/secop (R-TST-02).
 *
 * @return list<array<string, mixed>>
 */
function secopFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/fixtures/secop/{$name}.json"), true);
}
