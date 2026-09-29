<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 34 — US-053-RPT (features/US-053-RPT.feature, 2 casos): el
 * resumen de uso de cada organización, para el Super Administrador —
 * veedores activos y evidencias recibidas, publicadas, rechazadas y
 * retiradas, con la fecha de su última actividad (US-054-RPT).
 *
 * Las evidencias se crean directo en la base: aquí importa contarlas.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();
    Carbon::setTestNow('2026-09-29 12:00:00');
    $this->superAdmin = SuperAdmin::factory()->create();
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    $this->superAdmin->delete();
    Carbon::setTestNow();
});

/** $count evidences received by $tenant at $receivedAt, in that editorial status. */
function usageEvidence(Tenant $tenant, int $count, EditorialStatus $status, string $receivedAt = '2026-09-20 15:00:00'): void
{
    $tenant->run(function () use ($count, $status, $receivedAt) {
        // Un autor sin rol ni acceso: no es uno de los veedores activos de los escenarios.
        $veedor = OrganizationUser::create(['name' => 'Autor', 'email' => Str::random(12).'@correo.co', 'password' => 'Veeduria#2026', 'is_active' => false]);
        $worksite = Worksite::create(['latitude' => 11.2408, 'longitude' => -74.1990, 'located_at' => now()]);

        foreach (range(1, $count) as $n) {
            Report::create([
                'user_id' => $veedor->id,
                'worksite_id' => $worksite->id,
                'classification' => 'Avance',
                'latitude' => 11.2408,
                'longitude' => -74.1990,
                'accuracy_meters' => 15,
                'geofence_radius_meters' => 500,
                'captured_at' => $receivedAt,
                'received_at' => $receivedAt,
                'editorial_status' => $status,
            ]);
        }
    });
}

function usage(): array
{
    return test()->actingAs(test()->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/usage/data')->assertOk()->json('data');
}

// US-053-RPT ---------------------------------------------------------------------

it('Resumen de uso de una organización: 8 active veedores, 50 received, 30 published, 5 rejected and 2 withdrawn', function () {
    foreach (range(1, 8) as $n) {
        reportingMember($this->tenant, "veedor{$n}@correo.co");
    }
    usageEvidence($this->tenant, 30, EditorialStatus::Published);
    usageEvidence($this->tenant, 5, EditorialStatus::Rejected);
    usageEvidence($this->tenant, 2, EditorialStatus::Withdrawn);
    usageEvidence($this->tenant, 13, EditorialStatus::Hidden, '2026-09-25 15:00:00');

    expect(usage())->toBe([[
        'organization' => 'Veeduría Ciudadana Santa Marta',
        'status' => 'Activa',
        'active_observers' => 8,
        'received' => 50,
        'published' => 30,
        'rejected' => 5,
        'withdrawn' => 2,
        'last_activity_at' => '2026-09-25T10:00:00-05:00',
    ]]);
});

it('Un Administrador de Organización no accede al resumen global', function () {
    $administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);

    $this->actingAs($administrator, 'tenant')->getJson('http://govtrace.localhost/admin/usage/data')->assertUnauthorized();
    $this->actingAs($administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/admin/usage/data')->assertNotFound();
});

// Reglas de US-053-RPT -------------------------------------------------------------

it('counts as active neither a deactivated veedor, nor a pending invitation, nor an Administrador', function () {
    reportingMember($this->tenant, 'activo@correo.co');
    $inactive = reportingMember($this->tenant, 'inactivo@correo.co');
    $this->tenant->run(fn () => $inactive->update(['is_active' => false]));
    $invited = reportingMember($this->tenant, 'invitado@correo.co');
    $this->tenant->run(fn () => $invited->forceFill(['invitation_token_hash' => str_repeat('a', 64), 'invitation_expires_at' => now()->addDay()])->save());
    reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);

    expect(usage()[0]['active_observers'])->toBe(1);
});

it('lists every organization, with its status, as the list of organizations; a new one with no activity yet', function () {
    (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');
    $this->tenant->update(['status' => 'suspended']);

    expect(array_map(fn (array $row) => [$row['organization'], $row['status'], $row['received'], $row['last_activity_at']], usage()))->toBe([
        ['Veeduría Ciudadana Santa Marta', 'Suspendida', 0, null],
        ['Veeduría Ciénaga', 'Activa', 0, null],
    ]);
});

it('serves the usage screen of the global panel', function () {
    $this->withoutVite()->actingAs($this->superAdmin, 'web')
        ->get('http://govtrace.localhost/admin/usage')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Usage'));
});
