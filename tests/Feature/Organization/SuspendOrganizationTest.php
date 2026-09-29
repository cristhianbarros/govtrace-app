<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Publication\PublicTimeline;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 20 — Suspender y reactivar una organización (specs/PLAN.md).
 * Traduce features/US-003a.feature (6 casos) contra los endpoints reales:
 * POST /admin/organizations/{id}/suspend y /reactivate en el panel global,
 * y lo que eso cambia en el subdominio de la organización.
 *
 * Cada petición es un proceso nuevo en producción; en el test, tras una
 * petición al subdominio el contexto de la organización queda activo, así
 * que asSuperAdminOf() vuelve al central antes de ir al panel global.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const ORGANIZATION_SUSPENDED = 'La organización veedora ha sido temporalmente suspendida. Contacte a soporte';
const ORGANIZATION_SUSPENDED_NOTICE = '⚠️ Esta organización se encuentra suspendida temporalmente. Sus evidencias publicadas siguen disponibles solo para consulta.';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);

    // Antecedentes: la organización activa "Veeduría Ciudadana Santa Marta".
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@veeduria-smr.org');
    reportableContract('CO1.PCCNTR.1234567');
    $this->worksite = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
    $this->superAdmin = SuperAdmin::factory()->create();
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
    $this->superAdmin->delete();
});

/** POST /admin/organizations/{id}/{suspend|reactivate} en el panel global. */
function organizationDecision(string $decision): TestResponse
{
    if (tenant()) {
        tenancy()->end();
    }

    return test()->actingAs(test()->superAdmin, 'web')
        ->postJson('http://govtrace.localhost/admin/organizations/'.test()->tenant->id."/{$decision}");
}

function reportCount(): int
{
    return test()->tenant->run(fn () => Report::query()->count());
}

it('Suspensión de una organización activa: status "Suspendida", its users cannot reach their panel and it takes no new reports', function () {
    organizationDecision('suspend')
        ->assertOk()
        ->assertJson(['message' => 'Organización suspendida. Sus usuarios ya no pueden entrar; su mapa público sigue disponible.']);

    expect($this->tenant->fresh()->statusLabel())->toBe('Suspendida');

    // Su panel: ni por JSON ni por la pantalla, y la sesión se cierra.
    $this->actingAs($this->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/inbox')
        ->assertForbidden()
        ->assertJson(['message' => ORGANIZATION_SUSPENDED]);
    $this->assertGuest('tenant');
    $this->actingAs($this->administrator, 'tenant')->get('http://veeduria-smr.govtrace.localhost/admin/inbox')
        ->assertRedirect('http://veeduria-smr.govtrace.localhost/login');

    // Nadie vuelve a entrar mientras dure.
    $this->post('http://veeduria-smr.govtrace.localhost/login', ['email' => 'ana.perez@veeduria-smr.org', 'password' => 'Veeduria#2026'])
        ->assertSessionHasErrors(['email' => ORGANIZATION_SUSPENDED]);
    $this->assertGuest('tenant');

    // Ni reportes nuevos.
    sendReport($this->veedor)->assertForbidden();
    expect(reportCount())->toBe(0);

    $entry = AuditLog::query()->where('action', 'organization.suspended')->sole();
    expect($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_type)->toBe('super_admin')
        ->and($entry->actor_id)->toBe((string) $this->superAdmin->id)
        ->and($entry->before)->toBe(['status' => 'active'])
        ->and($entry->after)->toBe(['status' => 'suspended']);
});

it('Reactivación inmediata de una organización suspendida: "Activa", and its users are back right away', function () {
    organizationDecision('suspend')->assertOk();

    organizationDecision('reactivate')
        ->assertOk()
        ->assertJson(['message' => 'Organización reactivada. Sus usuarios ya pueden volver a entrar.']);

    expect($this->tenant->fresh()->statusLabel())->toBe('Activa')
        ->and(AuditLog::query()->where('action', 'organization.reactivated')->sole()->after)->toBe(['status' => 'active']);

    $this->actingAs($this->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/inbox')->assertOk();
    $this->post('http://veeduria-smr.govtrace.localhost/login', ['email' => 'carlos@veeduria-smr.org', 'password' => 'Veeduria#2026'])
        ->assertRedirect('http://veeduria-smr.govtrace.localhost/reports/new');
});

it('El mapa público de una organización suspendida sigue disponible con aviso', function () {
    $published = array_map(fn () => sealedReport($this->tenant, $this->veedor), range(1, 3));
    foreach ($published as $reportId) {
        editorialDecision($this->administrator, 'publish', $reportId)->assertOk();
    }

    organizationDecision('suspend')->assertOk();

    // Las 3 evidencias publicadas, con su sello para verificarlas.
    $timeline = $this->tenant->run(fn () => (new PublicTimeline)->handle($this->worksite->id));
    expect(array_column($timeline, 'report_id'))->toEqualCanonicalizing($published)
        ->and(array_filter(array_column(array_column($timeline, 'seal'), 'merkle_root')))->toHaveCount(3);

    // El sitio público responde, y cada pantalla pública recibe el aviso (it. 26 lo muestra en el mapa).
    $this->withoutVite()->get('http://veeduria-smr.govtrace.localhost/')->assertOk();
    $this->withoutVite()->get('http://veeduria-smr.govtrace.localhost/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('organizationNotice', ORGANIZATION_SUSPENDED_NOTICE));
});

it('No se puede suspender una organización ya suspendida', function () {
    organizationDecision('suspend')->assertOk();

    organizationDecision('suspend')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status' => 'La organización seleccionada ya se encuentra en estado suspendido.']);

    expect(AuditLog::query()->where('action', 'organization.suspended')->count())->toBe(1);
});

it('Un veedor en campo intenta enviar un reporte con su organización suspendida: forbidden, with the reason', function () {
    organizationDecision('suspend')->assertOk();

    // La app sincroniza sus 2 reportes pendientes: el servidor no acepta ninguno.
    foreach (range(1, 2) as $pending) {
        sendReport($this->veedor, ['captured_at' => now()->subHours($pending)->toIso8601String()])
            ->assertForbidden()
            ->assertJson(['message' => ORGANIZATION_SUSPENDED]);
    }

    expect(reportCount())->toBe(0);
});

it('Los reportes pendientes se envían si la organización se reactiva dentro de los 7 días', function () {
    organizationDecision('suspend')->assertOk();
    organizationDecision('reactivate')->assertOk();

    $reportId = sendReport($this->veedor, ['captured_at' => now()->subDays(3)->toIso8601String()])->assertCreated()->json('id');

    expect($this->tenant->run(fn () => Report::query()->findOrFail($reportId)->suspicious_capture_time))->toBeFalse();
});

// Reglas derivadas ------------------------------------------------------

it('does not reactivate an organization that is active', function () {
    organizationDecision('reactivate')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status' => 'La organización seleccionada ya se encuentra activa.']);
});

it('only lets the Super Administrador suspend or reactivate', function (string $decision) {
    $this->postJson('http://govtrace.localhost/admin/organizations/'.$this->tenant->id."/{$decision}")->assertUnauthorized();

    expect($this->tenant->fresh()->status)->toBe('active');
})->with(['suspend', 'reactivate']);

it('lists the organization as "Suspendida" in the global panel', function () {
    organizationDecision('suspend')->assertOk();

    $row = collect($this->actingAs($this->superAdmin, 'web')->getJson('http://govtrace.localhost/admin/organizations/data')->json('data'))->firstWhere('id', $this->tenant->id);

    expect($row['status'])->toBe('Suspendida');
});
