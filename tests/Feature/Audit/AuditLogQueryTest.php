<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Audit\AuditLabels;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
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

// It. 46d: filtros y frases -------------------------------------------------

function auditAs(string $who, array $filters = []): TestResponse
{
    $url = $who === 'global' ? 'http://govtrace.localhost/admin/audit/data' : 'http://veeduria-smr.govtrace.localhost/audit';
    $user = $who === 'global' ? test()->superAdmin : test()->administrator;

    return test()->actingAs($user, $who === 'global' ? 'web' : 'tenant')->getJson($url.'?'.http_build_query($filters));
}

it('El log se filtra por fecha, por quién lo hizo, por tipo de acción y, en el panel global, por organización', function () {
    $ids = fn (array $filters) => array_column(auditAs('global', $filters)->assertOk()->json('data'), 'id');
    $of = fn (array $entries) => array_map(fn (AuditLog $entry) => $entry->id, $entries);

    // Quién (parte del nombre, sin importar mayúsculas), y tipo de acción.
    expect($ids(['actor' => 'ana']))->toEqualCanonicalizing([$this->smrEntries[0]->id, $this->smrEntries[1]->id])
        ->and($ids(['group' => 'evidencias']))->toBe([$this->smrEntries[1]->id])
        ->and($ids(['group' => 'cuentas']))->toBe([$this->cienagaEntries[0]->id])
        ->and($ids(['organization' => $this->cienaga->id]))->toEqualCanonicalizing($of($this->cienagaEntries));

    // Todos a la vez: solo lo que cumple los cuatro.
    expect($ids(['actor' => 'root', 'group' => 'organizaciones', 'organization' => $this->smr->id]))->toBe([$this->smrEntries[2]->id]);

    // Fechas: los días de Colombia; la entrada de hace 5 minutos queda dentro de hoy y fuera de ayer.
    $today = now('America/Bogota')->toDateString();
    $yesterday = now('America/Bogota')->subDay()->toDateString();
    expect(count($ids(['from' => $today, 'to' => $today])))->toBe(5)
        ->and($ids(['to' => $yesterday]))->toBe([])
        ->and($ids(['from' => $today, 'group' => 'sellado']))->toBe([]);
});

it('keeps the filters when it pages', function () {
    foreach (range(1, 22) as $n) {
        AuditLog::record('evidence.published', $this->smr->id, 'organization_admin', '1', 'Ana Pérez', null, ['report_id' => $n]);
    }

    $second = auditAs('global', ['group' => 'evidencias', 'page' => 2])->assertOk()->json();

    expect($second['meta'])->toBe(['current_page' => 2, 'last_page' => 2, 'total' => 23])
        ->and($second['data'])->toHaveCount(3);
});

it('El Administrador de Organización no filtra por organización ni ve otra', function () {
    $entries = auditAs('organization', ['organization' => $this->cienaga->id])->assertOk()->json('data');

    expect(array_column($entries, 'id'))->toEqualCanonicalizing(array_map(fn (AuditLog $entry) => $entry->id, $this->smrEntries));
    expect(auditAs('organization', ['actor' => 'ana'])->json('data'))->toHaveCount(2);
});

it('Un filtro inválido se rechaza con un mensaje', function (array $filters, string $field) {
    auditAs('global', $filters)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'una fecha que no existe' => [['from' => '2026-02-31'], 'from'],
    'hasta antes que desde' => [['from' => '2026-09-30', 'to' => '2026-09-01'], 'to'],
    'un tipo de acción desconocido' => [['group' => 'hackeos'], 'group'],
    'una organización que no existe' => [['organization' => 'no-existe'], 'organization'],
]);

it('offers the filters their choices: the action types, and the organizations only to the Super Administrador', function () {
    $global = auditAs('global')->assertOk()->json('options');
    $own = auditAs('organization')->assertOk()->json('options');

    expect(array_column($global['groups'], 'key'))->toBe(['organizaciones', 'evidencias', 'cuentas', 'configuracion', 'sellado'])
        ->and(array_column($global['groups'], 'label'))->toBe(['Organizaciones y obras', 'Evidencias e informes', 'Cuentas e invitaciones', 'Configuración', 'Sellado'])
        ->and(array_column($global['organizations'], 'name'))->toEqualCanonicalizing(['Veeduría Ciudadana Santa Marta', 'Veeduría Ciénaga'])
        ->and($own['organizations'])->toBe([]);
});

it('Cada entrada se lee como una frase, con el detalle desplegable', function () {
    $entry = AuditLog::record('super_admin.deactivated', null, 'super_admin', '1', 'Ana Directora', ['email' => 'luis@govtrace.org', 'is_active' => true], ['email' => 'luis@govtrace.org', 'is_active' => false]);
    $system = AuditLog::record('citizen_reports.purged', null, null, null, null, null, ['purged' => 2]);

    $rows = collect(auditAs('global')->json('data'));

    expect($rows->firstWhere('id', $entry->id)['sentence'])->toBe('Ana Directora desactivó a un Super Administrador (luis@govtrace.org)')
        ->and($rows->firstWhere('id', $system->id)['sentence'])->toBe('El sistema borró informes ciudadanos descartados y correos de informes atendidos hace 30 días')
        ->and($rows->firstWhere('id', $this->smrEntries[0]->id)['sentence'])->toBe('Ana Pérez corrigió la ubicación de una obra');
});

it('Toda acción que se registra tiene su etiqueta y su tipo', function () {
    $recorded = collect(File::allFiles(app_path()))
        ->flatMap(fn ($file) => preg_match_all("/(?:action: |'action' => |audit\\()'([a-z_]+\\.[a-z_]+)'/", $file->getContents(), $matches) ? $matches[1] : [])
        ->unique()->sort()->values();

    expect($recorded->count())->toBeGreaterThan(30);
    foreach ($recorded as $action) {
        expect(AuditLabels::action($action))->not->toBe($action, "{$action} no tiene etiqueta")
            ->and(AuditLabels::groupOf($action))->not->toBeNull("{$action} no tiene tipo");
    }
    // Y ningún tipo etiquetado queda huérfano.
    expect(array_diff(AuditLabels::labeledActions(), array_filter(AuditLabels::labeledActions(), fn (string $action) => AuditLabels::groupOf($action) !== null)))->toBe([]);
});
