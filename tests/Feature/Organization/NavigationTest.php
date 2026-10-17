<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\OrganizationStatus;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 62 — US-067 (features/US-067.feature): saber dónde estoy y volver
 * a GovTrace. Cada veeduría vive en su subdominio y la sesión es de cada
 * dirección, así que la ruta de arriba necesita la dirección del Inicio de
 * GovTrace (APP_URL) también en el sitio de una veeduría, y el panel global
 * la del sitio de cada una. Lo demás de la ruta es interfaz (Breadcrumbs.test.js).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Notification::fake();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría BAF Medellín', 'veeduria-baf-medellin');
    $this->superAdmin = SuperAdmin::factory()->create();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('audit_logs')->delete();
    $this->superAdmin->delete();
});

it('En el sitio de una veeduría, la ruta lleva de vuelta a GovTrace: its pages share the address of the GovTrace home, in its own domain', function () {
    $this->get('http://veeduria-baf-medellin.govtrace.localhost/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Map')
            ->where('organization', 'Veeduría BAF Medellín')
            ->where('govtraceUrl', 'http://govtrace.localhost/'));

    // En el dominio central, la misma: el Inicio de GovTrace.
    tenancy()->end();
    $this->get('http://govtrace.localhost/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('organization', null)->where('govtraceUrl', 'http://govtrace.localhost/'));
});

it('Desde Organizaciones, el Super Administrador abre el sitio de cada veeduría: its address in its subdomain, and none once decommissioned', function () {
    $organizations = fn () => $this->actingAs($this->superAdmin, 'web')
        ->getJson('http://govtrace.localhost/admin/organizations/data')
        ->assertOk()
        ->json('data');

    expect($organizations())->toHaveCount(1)
        ->and($organizations()[0]['site_url'])->toBe('http://veeduria-baf-medellin.govtrace.localhost/');

    // Suspendida, su sitio sigue abierto, con su aviso.
    Tenant::query()->whereKey($this->tenant->id)->update(['status' => OrganizationStatus::Suspended->value]);
    expect($organizations()[0]['site_url'])->toBe('http://veeduria-baf-medellin.govtrace.localhost/');

    // Dada de baja, su sitio está fuera de línea.
    Tenant::query()->whereKey($this->tenant->id)->update(['status' => OrganizationStatus::Decommissioned->value, 'decommissioned_at' => now()]);
    expect($organizations()[0]['site_url'])->toBeNull();
});
