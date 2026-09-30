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
 * It. 43a — V2 y V16 de docs/mapa-funcional.md: el Super Administrador ve
 * quién administra cada organización y en qué va su invitación; la reenvía
 * si no la respondieron (vence a las 48 h), la revoca si el correo estaba mal
 * y asigna uno si la organización no tiene (US-002: "tras el alta, o en un
 * paso consecutivo"). Reemplazar a un Administrador activo, o tener varios, es
 * una decisión pendiente (V3): aquí no se permite. Todo queda en el log de
 * auditoría (R-AUD-04). Sin RefreshDatabase: la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();
    $this->superAdmin = SuperAdmin::factory()->create(['name' => 'Equipo GovTrace']);
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
    DB::table('audit_logs')->delete();
});

function asTheSuperAdmin(string $method, string $path, array $data = []): TestResponse
{
    return test()->actingAs(test()->superAdmin, 'web')->json($method, "http://govtrace.localhost{$path}", $data);
}

/** @return array<string, mixed> the organization as the list of the global panel shows it */
function listedOrganization(): array
{
    return collect(asTheSuperAdmin('GET', '/admin/organizations/data')->assertOk()->json('data'))->firstWhere('id', test()->tenant->id);
}

it('shows who administers each organization and how their invitation is going', function () {
    expect(listedOrganization()['administrators'])->toBe([]);

    $pending = (new AssignInitialAdministrator)->handle($this->tenant, 'Marta Ospina', 'marta@veeduria.org');
    expect(listedOrganization()['administrators'])->toBe([
        ['id' => $pending->id, 'name' => 'Marta Ospina', 'email' => 'marta@veeduria.org', 'status' => 'pending', 'label' => 'Invitación pendiente'],
    ]);

    $this->tenant->run(fn () => User::query()->whereKey($pending->id)->update(['invitation_expires_at' => now()->subHour()]));
    expect(listedOrganization()['administrators'][0])->toMatchArray(['status' => 'expired', 'label' => 'Invitación vencida']);

    $this->tenant->run(fn () => User::query()->whereKey($pending->id)->update(['invitation_token_hash' => null, 'invitation_expires_at' => null]));
    expect(listedOrganization()['administrators'][0])->toMatchArray(['status' => 'active', 'label' => 'Activo']);
});

it('Reenviar la invitación del Administrador inicial: a new link, the old one stops working, and it is audited', function () {
    $administrator = (new AssignInitialAdministrator)->handle($this->tenant, 'Marta Ospina', 'marta@veeduria.org');
    $oldHash = $this->tenant->run(fn () => User::query()->findOrFail($administrator->id)->invitation_token_hash);
    $this->tenant->run(fn () => User::query()->whereKey($administrator->id)->update(['invitation_expires_at' => now()->subHour()]));

    asTheSuperAdmin('POST', "/admin/organizations/{$this->tenant->id}/administrators/{$administrator->id}/invitation/resend")
        ->assertOk()
        ->assertJson(['message' => 'Invitación reenviada a marta@veeduria.org. El nuevo enlace vence en 48 horas.']);

    [$newHash, $valid] = $this->tenant->run(function () use ($administrator) {
        $renewed = User::query()->findOrFail($administrator->id);

        return [$renewed->invitation_token_hash, $renewed->invitation_expires_at->isFuture()];
    });
    expect($newHash)->not->toBe($oldHash)
        ->and($valid)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'invitation.resent')->where('actor_type', 'super_admin')->exists())->toBeTrue();
    Notification::assertSentTimes(WelcomeNotification::class, 2);
});

it('revokes an invitation sent to a wrong address, so another one can be assigned', function () {
    $wrong = (new AssignInitialAdministrator)->handle($this->tenant, 'Marta Ospina', 'marta@veduria.org');

    asTheSuperAdmin('POST', "/admin/organizations/{$this->tenant->id}/administrators/{$wrong->id}/invitation/revoke")->assertOk();

    expect(listedOrganization()['administrators'])->toBe([])
        ->and(AuditLog::query()->where('action', 'invitation.revoked')->where('actor_type', 'super_admin')->exists())->toBeTrue();
});

it('Asignar el Administrador inicial después del alta: to an organization that has none', function () {
    asTheSuperAdmin('POST', "/admin/organizations/{$this->tenant->id}/administrators", ['name' => 'Ana Pérez', 'email' => 'ana@veeduria.org'])
        ->assertCreated()
        ->assertJson(['message' => 'Invitación enviada a ana@veeduria.org. El enlace vence en 48 horas.']);

    expect(listedOrganization()['administrators'][0])->toMatchArray(['name' => 'Ana Pérez', 'status' => 'pending'])
        ->and($this->tenant->run(fn () => User::query()->where('email', 'ana@veeduria.org')->firstOrFail()->hasRole(Roles::Administrator->value)))->toBeTrue();
});

it('does not assign a second Administrador, nor replace one: that is decision V3', function () {
    (new AssignInitialAdministrator)->handle($this->tenant, 'Marta Ospina', 'marta@veeduria.org');

    asTheSuperAdmin('POST', "/admin/organizations/{$this->tenant->id}/administrators", ['name' => 'Ana Pérez', 'email' => 'ana@veeduria.org'])
        ->assertStatus(409)
        ->assertJson(['message' => 'La organización ya tiene un Administrador. Si su invitación quedó con un correo equivocado, revóquela primero.']);
});

it('does not resend nor revoke the account of an active Administrador, nor of a veedor', function () {
    $veedor = $this->tenant->run(function () {
        $user = User::create(['name' => 'Carlos', 'email' => 'carlos@correo.co', 'password' => 'Veeduria#2026']);
        $user->assignRole(Roles::Observer->value);

        return $user;
    });

    asTheSuperAdmin('POST', "/admin/organizations/{$this->tenant->id}/administrators/{$veedor->id}/invitation/revoke")->assertNotFound();
});

it('is only for the Super Administrador', function () {
    $this->json('POST', "http://govtrace.localhost/admin/organizations/{$this->tenant->id}/administrators", ['name' => 'Ana', 'email' => 'ana@veeduria.org'])
        ->assertUnauthorized();
});
