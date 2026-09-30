<?php

use App\Jobs\SyncSecopContracts;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/*
 * It. 43b — V15 de docs/mapa-funcional.md: el Super Administrador pide la
 * sincronización con SECOP II sin esperar a la madrugada (tras una caída de
 * la API, por ejemplo). Una a la vez: otra pedida en los 5 minutos
 * siguientes espera, para no martillar la API de datos.gov.co.
 */

beforeEach(function () {
    $this->artisan('migrate');
    Queue::fake();
    Cache::forget('secop.sync-now');
    $this->superAdmin = SuperAdmin::factory()->create();
});

it('Sincronizar SECOP a mano: queues the sync of every active organization, and says so', function () {
    $this->actingAs($this->superAdmin, 'web')->postJson('http://govtrace.localhost/admin/secop-health/sync')
        ->assertStatus(202)
        ->assertJson(['message' => 'Sincronización con SECOP II en marcha. En unos minutos verá el resultado aquí.']);

    Queue::assertPushed(SyncSecopContracts::class, 1);
});

it('does not queue another one within 5 minutes', function () {
    $this->actingAs($this->superAdmin, 'web')->postJson('http://govtrace.localhost/admin/secop-health/sync')->assertStatus(202);

    $this->actingAs($this->superAdmin, 'web')->postJson('http://govtrace.localhost/admin/secop-health/sync')
        ->assertStatus(429)
        ->assertJson(['message' => 'Ya se pidió una sincronización hace menos de 5 minutos. Espere su resultado.']);
    Queue::assertPushed(SyncSecopContracts::class, 1);
});

it('is only for the Super Administrador', function () {
    $this->postJson('http://govtrace.localhost/admin/secop-health/sync')->assertUnauthorized();
    Queue::assertNothingPushed();
});
