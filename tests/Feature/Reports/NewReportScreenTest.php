<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 16 — lo que la pantalla "Nuevo Reporte" de la PWA necesita del
 * servidor: la pantalla misma y "Buscar Obra" por HTTP. La regla de qué
 * contratos se pueden seleccionar ya se prueba en
 * SearchSelectableContractsTest (it. 8); aquí, el endpoint que la usa.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567', ['contractor_name' => 'Constructora del Caribe S.A.S.', 'process_number' => 'SMR-LP-012-2026']);
    // Fuera del territorio (Medellín): nunca aparece.
    reportableContract('CO1.PCCNTR.7654321', ['department_code' => '05', 'municipality_code' => '05001']);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
});

it('serves the "Nuevo Reporte" screen to the veedor', function () {
    $this->withoutVite()
        ->actingAs($this->veedor, 'tenant')
        ->get('http://veeduria-smr.govtrace.localhost/reports/new')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Veedor/NewReport'));
});

it('searches the territory contracts the veedor can report on, from 3 characters', function () {
    $search = fn (string $keyword) => $this->actingAs($this->veedor, 'tenant')
        ->getJson('http://veeduria-smr.govtrace.localhost/contracts/search?q='.urlencode($keyword));

    $search('Pavi')->assertOk()->assertExactJson(['data' => [[
        'secop_contract_id' => 'CO1.PCCNTR.1234567',
        'object' => 'Pavimentación Calle 30',
        'entity_name' => 'Alcaldía Distrital de Santa Marta',
        'contractor_name' => 'Constructora del Caribe S.A.S.',
        'process_number' => 'SMR-LP-012-2026',
        'status' => 'En ejecución',
    ]]]);

    $search('Pa')->assertOk()->assertExactJson(['data' => []]);
});

it('only lets the veedor use the screen and the search', function () {
    $administrator = reportingMember($this->tenant, 'admin@veeduria-smr.org', Roles::Administrator);

    $this->withoutVite()->actingAs($administrator, 'tenant')->get('http://veeduria-smr.govtrace.localhost/reports/new')->assertForbidden();
    $this->actingAs($administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/contracts/search?q=Pavi')->assertForbidden();
});
