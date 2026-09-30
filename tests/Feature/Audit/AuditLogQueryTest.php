<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 21 — Consulta del log de auditoría (specs/PLAN.md). Traduce
 * features/US-043-MON.feature (3 casos): el Super Administrador lo ve
 * todo, en el panel global (GET /admin/audit/data); el Administrador de
 * Organización solo lo de la suya, en su subdominio (GET /audit).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');

    $this->smr = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $this->cienaga = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');
    $this->administrator = reportingMember($this->smr, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->superAdmin = SuperAdmin::factory()->create(['name' => 'Root']);

    // Antecedentes: 3 entradas de Santa Marta y 2 de Ciénaga, y nada más.
    DB::table('audit_logs')->delete();
    $this->travel(-5)->minutes();
    $this->smrEntries = [
        AuditLog::record('worksite.location_corrected', $this->smr->id, 'organization_admin', (string) $this->administrator->id, 'Ana Pérez', ['latitude' => 11.2], ['latitude' => 11.2408]),
        AuditLog::record('evidence.published', $this->smr->id, 'organization_admin', (string) $this->administrator->id, 'Ana Pérez', ['editorial_status' => 'hidden'], ['editorial_status' => 'published']),
    ];
    $this->travelBack();
    $this->smrEntries[] = AuditLog::record('organization.legal_data_updated', $this->smr->id, 'super_admin', (string) $this->superAdmin->id, 'Root', ['nit' => '900123456-8'], ['nit' => '901234567-7']);
    $this->cienagaEntries = [
        AuditLog::record('observer.deactivated', $this->cienaga->id, 'organization_admin', '1', 'Pedro Ruiz', ['is_active' => true], ['is_active' => false]),
        AuditLog::record('organization.suspended', $this->cienaga->id, 'super_admin', (string) $this->superAdmin->id, 'Root', ['status' => 'active'], ['status' => 'suspended']),
    ];
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('audit_logs')->delete();
    $this->superAdmin->delete();
});

it('El Super Administrador ve todo el log: each entry says who, when, the action, the value before and after', function () {
    $entries = $this->actingAs($this->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/audit/data')->assertOk()->json('data');

    expect($entries)->toHaveCount(5);

    $nitChange = collect($entries)->firstWhere('id', $this->smrEntries[2]->id);
    expect($nitChange)->toMatchArray([
        'organization' => 'Veeduría Ciudadana Santa Marta',
        'actor' => 'Root · Super Administrador',
        'action' => 'Cambió los datos legales (NIT o inscripción)',
        'before' => ['nit' => '900123456-8'],
        'after' => ['nit' => '901234567-7'],
    ])->and($nitChange['created_at'])->toBe($this->smrEntries[2]->created_at->toIso8601String());

    // Lo más reciente primero.
    expect(end($entries)['action'])->toBe('Corrigió la ubicación de una obra');
});

it('El Administrador de Organización ve solo lo de su organización', function () {
    $entries = $this->actingAs($this->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/audit')->assertOk()->json('data');

    expect(array_column($entries, 'id'))->toEqualCanonicalizing(array_map(fn (AuditLog $entry) => $entry->id, $this->smrEntries))
        ->and(collect($entries)->firstWhere('id', $this->smrEntries[1]->id)['actor'])->toBe('Ana Pérez · Administrador de Organización');
});

it('Un Administrador de Organización no accede a entradas de otra organización', function () {
    $this->actingAs($this->administrator, 'tenant')
        ->getJson('http://veeduria-smr.govtrace.localhost/audit/'.$this->cienagaEntries[0]->id)
        ->assertNotFound();

    $this->actingAs($this->administrator, 'tenant')
        ->getJson('http://veeduria-smr.govtrace.localhost/audit/'.$this->smrEntries[0]->id)
        ->assertOk()
        ->assertJsonPath('data.action', 'Corrigió la ubicación de una obra');
});

// Reglas derivadas ------------------------------------------------------

it('does not show the log to a veedor', function () {
    $veedor = reportingMember($this->smr, 'carlos@correo.co');

    $this->actingAs($veedor, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/audit')->assertForbidden();
});

it('pages the log, 20 entries at a time', function () {
    foreach (range(1, 22) as $n) {
        AuditLog::record('evidence.published', $this->smr->id, 'organization_admin', '1', 'Ana Pérez', null, ['report_id' => $n]);
    }

    $first = $this->actingAs($this->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/audit/data')->json();

    expect($first['data'])->toHaveCount(20)
        ->and($first['meta'])->toBe(['current_page' => 1, 'last_page' => 2, 'total' => 27]);
});

it('serves the audit screens: the global one and the organization one', function () {
    $this->withoutVite()->actingAs($this->superAdmin, 'web')->get('http://govtrace.localhost/admin/audit')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Audit'));
    $this->withoutVite()->actingAs($this->administrator, 'tenant')->get('http://veeduria-smr.govtrace.localhost/admin/audit')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Audit'));
});
