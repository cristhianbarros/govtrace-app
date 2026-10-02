<?php

use App\Application\Organization\AssignInitialAdministrator;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
 * Iteración 43j — V3 de docs/mapa-funcional.md (features/US-061-USR.feature):
 * una organización puede tener varios administradores, para no quedar sin
 * quién la gestione si uno pierde el acceso o se va. El Super Administrador
 * los agrega, los desactiva y los reactiva, sin dejarla nunca sin uno activo;
 * un Administrador invita a otro desde su panel. Todo va al log de auditoría.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const ONLY_ACTIVE_ADMINISTRATOR = 'No se puede desactivar al único Administrador activo de la organización. Agregue otro y espere a que active su cuenta.';
const ADMIN_HOST = 'http://veeduria-smr.govtrace.localhost';

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();
    $this->superAdmin = SuperAdmin::factory()->create(['name' => 'Equipo GovTrace']);
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $this->marta = reportingMember($this->tenant, 'marta@veeduria.org', Roles::Administrator);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
    DB::table('audit_logs')->delete();
    $this->superAdmin->delete();
});

function fromThePanel(string $method, string $path, array $data = []): TestResponse
{
    test()->flushSession();

    return test()->actingAs(test()->superAdmin, 'web')->json($method, "http://govtrace.localhost{$path}", $data);
}

function asMember(User $member, string $method, string $path, array $data = []): TestResponse
{
    test()->flushSession();
    tenancy()->end();

    return test()->actingAs($member, 'tenant')->json($method, ADMIN_HOST.$path, $data);
}

/** @return list<array<string, mixed>> the administrators of the organization, as the global panel lists them */
function administratorsInThePanel(): array
{
    return collect(fromThePanel('GET', '/admin/organizations/data')->assertOk()->json('data'))->firstWhere('id', test()->tenant->id)['administrators'];
}

function freshMember(Tenant $tenant, int $id): User
{
    return $tenant->run(fn () => User::query()->findOrFail($id));
}

it('El Super Administrador agrega otro Administrador: the invitation arrives, and the organization has two', function () {
    fromThePanel('POST', "/admin/organizations/{$this->tenant->id}/administrators", ['name' => 'Ana Pérez', 'email' => 'ana@veeduria.org'])
        ->assertCreated()
        ->assertJson(['message' => 'Invitación enviada a ana@veeduria.org. El enlace vence en 48 horas.']);

    $ana = $this->tenant->run(fn () => User::query()->where('email', 'ana@veeduria.org')->firstOrFail());
    Notification::assertSentTo($ana, WelcomeNotification::class);
    expect(collect(administratorsInThePanel())->pluck('status', 'email')->all())->toBe(['marta@veeduria.org' => 'active', 'ana@veeduria.org' => 'pending'])
        ->and(AuditLog::query()->where('action', 'organization.administrator_assigned')->exists())->toBeTrue();
});

it('Un Administrador invita a otro administrador: from "Veedores", and the log says who invited', function () {
    asMember($this->marta, 'POST', '/administrators/invite', ['name' => 'Ana Pérez', 'email' => 'ana@veeduria.org'])
        ->assertCreated()
        ->assertJson(['message' => 'Invitación enviada a ana@veeduria.org. El enlace vence en 48 horas.']);

    $ana = $this->tenant->run(fn () => User::query()->where('email', 'ana@veeduria.org')->firstOrFail());
    Notification::assertSentTo($ana, WelcomeNotification::class);
    expect($this->tenant->run(fn () => $ana->fresh()->hasRole(Roles::Administrator->value)))->toBeTrue();
    $entry = AuditLog::query()->where('action', 'organization.administrator_invited')->sole();
    expect($entry->actor_type)->toBe('organization_admin')
        ->and($entry->actor_id)->toBe((string) $this->marta->id)
        ->and($entry->after['email'])->toBe('ana@veeduria.org');
});

it('lets each Administrador see the administrators of the organization', function () {
    (new AssignInitialAdministrator)->handle($this->tenant, 'Ana Pérez', 'ana@veeduria.org');

    expect(asMember($this->marta, 'GET', '/administrators')->assertOk()->json('data'))
        ->toMatchArray([
            ['id' => $this->marta->public_id, 'name' => 'Miembro de prueba', 'email' => 'marta@veeduria.org', 'status' => 'active', 'label' => 'Activo'],
            1 => ['id' => freshAdministratorId('ana@veeduria.org'), 'name' => 'Ana Pérez', 'email' => 'ana@veeduria.org', 'status' => 'pending', 'label' => 'Invitación pendiente'],
        ]);
});

function freshAdministratorId(string $email): string
{
    return test()->tenant->run(fn () => User::query()->where('email', $email)->value('public_id')); // it. 46c
}

it('El Super Administrador desactiva a un Administrador que se fue: its next request is refused, and the log keeps it', function () {
    $ana = reportingMember($this->tenant, 'ana@veeduria.org', Roles::Administrator);

    fromThePanel('POST', "/admin/organizations/{$this->tenant->id}/administrators/{$this->marta->public_id}/deactivate")
        ->assertOk()
        ->assertJson(['message' => 'Administrador desactivado. Su sesión quedó cerrada y ya no puede entrar.']);

    expect(freshMember($this->tenant, $this->marta->id)->is_active)->toBeFalse();
    asMember(freshMember($this->tenant, $this->marta->id), 'GET', '/inbox')->assertForbidden();
    expect(AuditLog::query()->where('action', 'organization.administrator_deactivated')->sole()->before['email'])->toBe('marta@veeduria.org')
        ->and(freshMember($this->tenant, $ana->id)->is_active)->toBeTrue();
});

it('El Super Administrador reactiva a un Administrador', function () {
    reportingMember($this->tenant, 'ana@veeduria.org', Roles::Administrator);
    $this->tenant->run(fn () => User::query()->whereKey($this->marta->id)->update(['is_active' => false]));

    fromThePanel('POST', "/admin/organizations/{$this->tenant->id}/administrators/{$this->marta->public_id}/reactivate")
        ->assertOk()
        ->assertJson(['message' => 'Administrador reactivado. Ya puede entrar otra vez.']);

    expect(freshMember($this->tenant, $this->marta->id)->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'organization.administrator_reactivated')->exists())->toBeTrue();
});

it('No se desactiva al único Administrador activo: a pending invitation does not count', function () {
    (new AssignInitialAdministrator)->handle($this->tenant, 'Ana Pérez', 'ana@veeduria.org');

    fromThePanel('POST', "/admin/organizations/{$this->tenant->id}/administrators/{$this->marta->public_id}/deactivate")
        ->assertStatus(409)
        ->assertJson(['message' => ONLY_ACTIVE_ADMINISTRATOR]);

    expect(freshMember($this->tenant, $this->marta->id)->is_active)->toBeTrue();
});

it('Un Administrador no desactiva administradores: the team screen only reaches veedores', function () {
    $ana = reportingMember($this->tenant, 'ana@veeduria.org', Roles::Administrator);

    asMember($this->marta, 'POST', "/observers/{$ana->public_id}/deactivate")->assertNotFound();

    expect(freshMember($this->tenant, $ana->id)->is_active)->toBeTrue();
});

it('Un veedor no invita administradores', function () {
    $veedor = reportingMember($this->tenant, 'carlos@correo.co');

    asMember($veedor, 'POST', '/administrators/invite', ['name' => 'Ana Pérez', 'email' => 'ana@veeduria.org'])->assertForbidden();
});

it('El correo de un administrador nuevo no puede estar ya en la organización', function () {
    reportingMember($this->tenant, 'carlos@correo.co');

    asMember($this->marta, 'POST', '/administrators/invite', ['name' => 'Carlos', 'email' => 'carlos@correo.co'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'El correo electrónico ya se encuentra registrado en el sistema.']);
});
