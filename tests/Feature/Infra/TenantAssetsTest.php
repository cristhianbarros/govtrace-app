<?php

use App\Application\Organization\RegisterOrganization;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/*
 * Iteración 30 — hallazgo de la prueba de extremo a extremo: en el
 * subdominio de una organización, asset() mandaba el frontend compilado
 * (public/build) a la ruta de archivos de la organización de Stancl
 * (/tenancy/assets/…), que responde 404: en un navegador de verdad, sus
 * pantallas cargaban sin JavaScript ni estilos. Los tests de Inertia usan
 * withoutVite() y no lo veían. Los archivos de cada organización (su logo)
 * tienen su propia ruta (US-007).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('audit_logs')->delete();
});

it('serves the built frontend from /build on an organization subdomain too', function () {
    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    $url = $tenant->run(fn () => asset('build/assets/app.js'));

    expect($url)->not->toContain('tenancy/assets')
        ->and($url)->toEndWith('/build/assets/app.js');
});
