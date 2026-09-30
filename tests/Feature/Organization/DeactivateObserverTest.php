<?php

use App\Application\Organization\AcceptInvitation;
use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 20 — Desactivar y reactivar a un veedor de campo
 * (specs/PLAN.md). Traduce features/US-006.feature (3 casos) y
 * features/US-041-USR.feature (2 casos) contra los endpoints reales:
 * POST /observers/{id}/deactivate y /reactivate.
 *
 * "Su sesión deja de ser válida de inmediato": en cada petición el servidor
 * lee de la base si la cuenta sigue activa — no confía en el usuario que el
 * guard tiene en memoria, que en este test (actingAs) es el mismo objeto de
 * antes de desactivarlo.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const ACCOUNT_DEACTIVATED = 'Su cuenta ha sido desactivada. No es posible sincronizar nuevos reportes.';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);

    // Antecedentes: el Administrador y "carlos@correo.co", veedor activo.
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

/** POST /observers/{id}/{deactivate|reactivate}, como $member. */
function observerDecision(OrganizationUser $member, string $decision, OrganizationUser $observer): TestResponse
{
    return test()->actingAs($member, 'tenant')->postJson("http://veeduria-smr.govtrace.localhost/observers/{$observer->id}/{$decision}");
}

function freshObserver(OrganizationUser $observer): OrganizationUser
{
    return test()->tenant->run(fn () => OrganizationUser::query()->findOrFail($observer->id));
}

it('Desactivación con revocación inmediata de sesiones: "Inactivo", and the open session stops working at once', function () {
    // Su sesión abierta en el teléfono funciona.
    $this->actingAs($this->veedor, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/contracts/search?q=Pavi')->assertOk();

    observerDecision($this->administrator, 'deactivate', $this->veedor)
        ->assertOk()
        ->assertJson(['message' => 'Veedor desactivado. Su sesión quedó cerrada y ya no puede enviar reportes.']);

    expect(freshObserver($this->veedor)->statusLabel())->toBe('Inactivo');

    // La misma sesión, en su siguiente petición: rechazada y cerrada.
    $this->actingAs($this->veedor, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/contracts/search?q=Pavi')
        ->assertForbidden()
        ->assertJson(['message' => ACCOUNT_DEACTIVATED]);
    $this->assertGuest('tenant');

    // Y si abre una pantalla, vuelve al inicio de sesión con el motivo.
    $this->actingAs($this->veedor, 'tenant')->get('http://veeduria-smr.govtrace.localhost/reports/new')
        ->assertRedirect('http://veeduria-smr.govtrace.localhost/login')
        ->assertSessionHasErrors(['email' => 'Su cuenta se encuentra desactivada. Comuníquese con el administrador de su organización.']);
});

it('Los reportes previos del veedor desactivado se conservan, con su autoría', function () {
    $reports = array_map(fn () => sealedReport($this->tenant, $this->veedor), range(1, 6));

    observerDecision($this->administrator, 'deactivate', $this->veedor)->assertOk();

    $kept = $this->tenant->run(fn () => Report::query()->with('seal')->whereIn('id', $reports)->get());
    expect($kept)->toHaveCount(6)
        ->and($kept->pluck('user_id')->unique()->all())->toBe([$this->veedor->id])
        ->and($kept->pluck('seal.status')->map->label()->unique()->all())->toBe(['Sellada']);
});

it('El veedor desactivado intenta sincronizar su cola local: forbidden, and the report is not taken', function () {
    observerDecision($this->administrator, 'deactivate', $this->veedor)->assertOk();

    sendReport($this->veedor)
        ->assertForbidden()
        ->assertJson(['message' => ACCOUNT_DEACTIVATED]);

    expect($this->tenant->run(fn () => Report::query()->count()))->toBe(0);
});

it('Reactivación de un veedor: logs in again with his password, and the audit log records it', function () {
    observerDecision($this->administrator, 'deactivate', $this->veedor)->assertOk();

    observerDecision($this->administrator, 'reactivate', $this->veedor)
        ->assertOk()
        ->assertJson(['message' => 'Veedor reactivado. Ya puede volver a iniciar sesión.']);

    $this->post('http://veeduria-smr.govtrace.localhost/login', ['email' => 'carlos@correo.co', 'password' => 'Veeduria#2026'])
        ->assertRedirect('http://veeduria-smr.govtrace.localhost/reports/new');

    $entry = AuditLog::query()->where('action', 'observer.reactivated')->sole();
    expect($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_type)->toBe('organization_admin')
        ->and($entry->actor_id)->toBe((string) $this->administrator->id)
        ->and($entry->before)->toBe(['user_id' => $this->veedor->id, 'email' => 'carlos@correo.co', 'is_active' => false])
        ->and($entry->after)->toBe(['user_id' => $this->veedor->id, 'email' => 'carlos@correo.co', 'is_active' => true]);
});

it('Un veedor no puede reactivar cuentas', function () {
    $laura = reportingMember($this->tenant, 'laura@correo.co');
    observerDecision($this->administrator, 'deactivate', $this->veedor)->assertOk();

    observerDecision($laura, 'reactivate', $this->veedor)->assertForbidden();

    expect(freshObserver($this->veedor)->is_active)->toBeFalse();
});

// Reglas derivadas ------------------------------------------------------

it('records every deactivation in the audit log', function () {
    observerDecision($this->administrator, 'deactivate', $this->veedor)->assertOk();

    $entry = AuditLog::query()->where('action', 'observer.deactivated')->sole();
    expect($entry->actor_id)->toBe((string) $this->administrator->id)
        ->and($entry->after)->toBe(['user_id' => $this->veedor->id, 'email' => 'carlos@correo.co', 'is_active' => false]);
});

it('says so when the veedor is already in that state', function (string $decision, string $message) {
    if ($decision === 'deactivate') {
        observerDecision($this->administrator, 'deactivate', $this->veedor)->assertOk();
    }

    observerDecision($this->administrator, $decision, $this->veedor)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status' => $message]);
})->with([
    'desactivar dos veces' => ['deactivate', 'El veedor ya está inactivo.'],
    'reactivar a uno activo' => ['reactivate', 'El veedor ya está activo.'],
]);

it('only deactivates veedores, not Administradores', function () {
    $otherAdministrator = reportingMember($this->tenant, 'pedro@veeduria-smr.org', Roles::Administrator);

    observerDecision($this->administrator, 'deactivate', $otherAdministrator)->assertNotFound();

    expect(freshObserver($otherAdministrator)->is_active)->toBeTrue();
});

it('voids the invitation link of a veedor deactivated before accepting it', function () {
    $invited = $this->tenant->run(fn () => (new InviteObserver)->handle('laura@correo.co'));
    $plain = 'token-del-correo';
    $this->tenant->run(fn () => $invited->forceFill(['invitation_token_hash' => hash('sha256', $plain)])->save());

    observerDecision($this->administrator, 'deactivate', $invited)->assertOk();

    expect($this->tenant->run(fn () => (new AcceptInvitation)->isValid(freshObserver($invited), $plain)))->toBeFalse()
        ->and(freshObserver($invited)->statusLabel())->toBe('Inactivo');
});

it('lists the veedores with their id, so the panel can act on each one', function () {
    observerDecision($this->administrator, 'deactivate', $this->veedor)->assertOk();
    // US-057-LEG: cuándo declaró no tener impedimentos para ser veedor.
    $declaredAt = $this->tenant->run(fn () => $this->veedor->fresh()->impediments_declared_at->toIso8601String());

    expect($this->actingAs($this->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/observers')->json('data'))->toBe([
        ['id' => $this->veedor->id, 'email' => 'carlos@correo.co', 'status' => 'Inactivo', 'impediments_declared_at' => $declaredAt],
    ]);
});
