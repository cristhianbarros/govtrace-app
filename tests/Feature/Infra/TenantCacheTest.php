<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/*
 * Iteración 30 — hallazgo de la prueba de extremo a extremo: con la caché en
 * la base de datos (CACHE_STORE=database, como en desarrollo y en
 * producción), crear una organización fallaba — su migración limpia la caché
 * de permisos y la buscaba en la base de la organización —, y dentro de una
 * organización la caché exigía etiquetas, que ese almacén no tiene. Los tests
 * usan "array", que sí las tiene, y no lo veían.
 *
 * Ahora la caché de base de datos vive siempre en la base central, y cada
 * organización tiene su propio prefijo.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Notification::fake();
    config(['cache.default' => 'database']);
    Cache::clearResolvedInstances();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('cache')->delete();
    DB::table('audit_logs')->delete();
});

it('creates an organization and logs its members in with the database cache store, as in production', function () {
    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($tenant, ['47']);
    reportingMember($tenant, 'carlos@correo.co');

    $this->post('http://veeduria-smr.govtrace.localhost/login', ['email' => 'carlos@correo.co', 'password' => 'Veeduria#2026'])
        ->assertRedirect('http://veeduria-smr.govtrace.localhost/reports/new');

    $this->assertAuthenticated('tenant');
});

it('keeps the cache of each organization apart, in the central database', function () {
    $santaMarta = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $cienaga = (new RegisterOrganization)->handle('901234567-7', 'Veeduría Ciénaga', 'veeduria-cienaga');

    $santaMarta->run(fn () => Cache::put('bandeja', 'de Santa Marta', 60));

    expect($cienaga->run(fn () => Cache::get('bandeja')))->toBeNull()
        ->and(Cache::get('bandeja'))->toBeNull()
        ->and($santaMarta->run(fn () => Cache::get('bandeja')))->toBe('de Santa Marta')
        ->and(DB::table('cache')->where('key', 'like', '%bandeja')->count())->toBe(1);
});
