<?php

use App\Application\Organization\RegisterOrganization;
use App\Infrastructure\Tenancy\Tenant;
use Inertia\Testing\AssertableInertia;

/*
 * It. 43b — V6 de docs/mapa-funcional.md: la app del veedor se instala en el
 * celular. Cada veeduría tiene su manifiesto, con su nombre: el ícono en la
 * pantalla de inicio abre directo en "Nuevo Reporte", sin recordar la
 * dirección. El Service Worker (US-018) ya la abre sin señal.
 * Sin RefreshDatabase: la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
});

it('La app del veedor se instala en el celular: each veeduría has its own manifest, with its name, opening on Nuevo Reporte', function () {
    $this->tenant->forceFill(['display_name' => 'Ojo Ciudadano SMR'])->save();

    $manifest = $this->get('http://veeduria-smr.govtrace.localhost/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json');

    expect($manifest->json())->toMatchArray([
        'name' => 'GovTrace · Ojo Ciudadano SMR',
        'short_name' => 'Ojo Ciudada…',
        'start_url' => '/reports/new',
        'scope' => '/',
        'display' => 'standalone',
        'lang' => 'es-CO',
    ])
        ->and(collect($manifest->json('icons'))->pluck('sizes')->all())->toBe(['192x192', '512x512', 'any']);

    foreach (['/pwa/govtrace-192.png', '/pwa/govtrace-512.png', '/pwa/govtrace.svg'] as $icon) {
        expect(file_exists(public_path($icon)))->toBeTrue();
    }
});

it('links the manifest from the pages of a veeduría, and not from the global panel', function () {
    $this->withoutVite()->get('http://veeduria-smr.govtrace.localhost/')->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);
    tenancy()->end(); // en el servidor cada petición empieza de cero; en el test, se cierra a mano

    $this->withoutVite()->get('http://govtrace.localhost:8080/')->assertDontSee('rel="manifest"', false);
});

/*
 * It. 43b — V14: el validador lleva al programa independiente del repositorio
 * abierto (US-046-INT), para quien no quiere depender de la página.
 */
it('gives the validator the way to the independent verifier', function () {
    $this->withoutVite()->get('http://veeduria-smr.govtrace.localhost/verify')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('verifierUrl', 'https://github.com/cristhianbarros/govtrace-app/tree/main/tools/verify'));
});
