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
 * Iteración 26 — El sitio público de cada organización (specs/PLAN.md): el
 * mapa en "/" y la vista de una obra en "/worksite/{id}", sin sesión
 * (R-VER-02, US-017 "La vista no exige inicio de sesión"). Las pantallas
 * piden sus datos al API público de la it. 24; sus estados se prueban con
 * Vitest.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Notification::fake();
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

it('serves the public map of the organization at its root, without a session', function () {
    $this->withoutVite()->get('http://veeduria-smr.govtrace.localhost/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Map')
            ->where('organization', 'Veeduría Ciudadana Santa Marta')
            ->where('organizationNotice', null));
});

it('La vista no exige inicio de sesión: serves the view of a worksite, with its id', function () {
    reportableContract('CO1.PCCNTR.1234567');
    $worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());

    $this->withoutVite()->get("http://veeduria-smr.govtrace.localhost/worksite/{$worksite->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/Worksite')->where('worksiteId', $worksite->id));
});

it('keeps both pages open while the organization is suspended, with the notice (R-AUD-01)', function () {
    (new ChangeOrganizationStatus)->handle($this->tenant, OrganizationStatus::Suspended, 'organization.suspended');

    foreach (['/', '/worksite/1'] as $path) {
        $this->withoutVite()->get("http://veeduria-smr.govtrace.localhost{$path}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('organizationNotice', '⚠️ Esta organización se encuentra suspendida temporalmente. Sus evidencias publicadas siguen disponibles solo para consulta.'));
    }
});
