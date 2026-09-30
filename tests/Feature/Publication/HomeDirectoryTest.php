<?php

use App\Application\Organization\ChangeOrganizationStatus;
use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\OrganizationStatus;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * It. 40d — V5 de docs/mapa-funcional.md: el Inicio de GovTrace lleva al mapa
 * de cada veeduría. No es un mapa global (R-MAP-01): es el directorio, con el
 * nombre, el territorio y el enlace de cada una. Las suspendidas siguen, con
 * su aviso (su mapa sigue abierto, R-AUD-01); las dadas de baja, no.
 * Sin RefreshDatabase: registrar una organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Notification::fake();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
    DB::table('organization_territories')->delete();
});

it('Llegar al mapa de una veeduría desde el Inicio: lists every open veeduría with its territory and the way to its map', function () {
    $santaMarta = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($santaMarta, ['47']);
    $cienaga = (new RegisterOrganization)->handle('901555888-3', 'Ojo Ciudadano Ciénaga', 'ojo-cienaga');
    (new ConfigureTerritory)->handle($cienaga, ['47189']);
    (new ChangeOrganizationStatus)->handle($cienaga, OrganizationStatus::Suspended, 'organization.suspended');
    $closed = (new RegisterOrganization)->handle('901555999-2', 'Veeduría Cerrada', 'veeduria-cerrada');
    $closed->forceFill(['status' => OrganizationStatus::Decommissioned->value])->save();

    $this->withoutVite()->get('http://govtrace.localhost:8080/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Home')->where('organizations', [
            ['name' => 'Ojo Ciudadano Ciénaga', 'territory' => 'Ciénaga', 'url' => 'http://ojo-cienaga.govtrace.localhost/', 'suspended' => true],
            ['name' => 'Veeduría Ciudadana Santa Marta', 'territory' => 'Magdalena', 'url' => 'http://veeduria-smr.govtrace.localhost/', 'suspended' => false],
        ]));
});

it('shows the name the organization chose, and an empty directory when there is none yet', function () {
    $this->withoutVite()->get('http://govtrace.localhost:8080/')
        ->assertInertia(fn (Assert $page) => $page->where('organizations', []));

    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $tenant->forceFill(['display_name' => 'Ojo Ciudadano SMR'])->save();

    $this->withoutVite()->get('http://govtrace.localhost:8080/')
        ->assertInertia(fn (Assert $page) => $page->where('organizations.0.name', 'Ojo Ciudadano SMR')->where('organizations.0.territory', null));
});
