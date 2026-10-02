<?php

use App\Application\Organization\AssignInitialAdministrator;
use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Application\Privacy\DataPolicy;
use App\Domain\Audit\AuditLabels;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\User;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * Iteración 44e — La política de tratamiento de datos y la autorización
 * (features/US-058-LEG.feature). La Ley 1581 de 2012 pide una política con
 * un contenido mínimo (Decreto 1074 de 2015, art. 2.2.2.25.3.1) y una
 * autorización previa, expresa e informada, con su prueba (art. 9).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const POLICY_HOST = 'http://veeduria-smr.govtrace.localhost';
const AUTHORIZATION_REQUIRED = 'Para crear su cuenta, autorice el tratamiento de sus datos personales.';

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    config(['privacy.controller' => [
        'name' => 'Fundación GovTrace',
        'identification' => 'NIT 901555888-3',
        'address' => 'Calle 10 # 20-30, Medellín',
        'email' => 'datos@govtrace.org',
        'phone' => '604 000 0000',
    ]]);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('audit_logs')->delete();
});

/** @return array{0: User, 1: string} an invited member and the token of their link */
function invitedMember(Closure $invite): array
{
    return test()->tenant->run(function () use ($invite) {
        $member = $invite();
        $plain = Str::random(64);
        $member->forceFill(['invitation_token_hash' => hash('sha256', $plain)])->save();

        return [$member, $plain];
    });
}

it('Cualquiera lee la política de tratamiento de datos: on the central domain and on every veeduría, without an account', function (string $host) {
    $this->get("{$host}/privacidad")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Public/Privacy')
        ->where('policy.version', DataPolicy::VERSION)
        ->where('policy.effective_date', '02/10/2026')
        ->where('policy.controller.name', 'Fundación GovTrace')
        ->where('policy.controller.email', 'datos@govtrace.org')
        ->where('policy.missing', []));
})->with(['central' => 'http://govtrace.localhost', 'veeduría' => POLICY_HOST]);

it('Sin los datos del responsable la política es un borrador: it says which ones are missing', function () {
    config(['privacy.controller' => ['name' => 'Fundación GovTrace', 'identification' => null, 'address' => null, 'email' => 'datos@govtrace.org', 'phone' => null]]);

    $this->get('http://govtrace.localhost/privacidad')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('policy.missing', ['la identificación (NIT)', 'el domicilio y la dirección', 'el teléfono']));
});

it('Autorizo el tratamiento de mis datos al activar mi cuenta: the date, the version of the policy and the audit log', function () {
    [$veedor, $token] = invitedMember(fn () => (new InviteObserver)->handle('carlos@correo.co'));

    $this->get(POLICY_HOST."/set-password/{$veedor->id}?token={$token}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('dataPolicyUrl', '/privacidad'));
    tenancy()->end();

    $this->travelTo('2026-09-30 15:00:00');
    $this->post(POLICY_HOST."/set-password/{$veedor->id}", [
        'token' => $token, 'password' => 'Veeduria#2026', 'password_confirmation' => 'Veeduria#2026',
        'declaration' => true, 'data_authorization' => true,
    ])->assertRedirect(POLICY_HOST.'/reports/new');
    tenancy()->end();

    // Dentro de la organización: el cast de la fecha lee el formato de su conexión.
    [$authorizedAt, $version] = $this->tenant->run(function () use ($veedor) {
        $fresh = User::query()->findOrFail($veedor->id);

        return [$fresh->data_authorized_at->toIso8601String(), $fresh->data_policy_version];
    });
    $entry = AuditLog::query()->where('action', 'privacy.data_authorized')->sole();
    expect($authorizedAt)->toBe('2026-09-30T15:00:00+00:00')
        ->and($version)->toBe(DataPolicy::VERSION)
        ->and($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_type)->toBe('observer')
        ->and($entry->after)->toMatchArray(['user_id' => $veedor->id, 'email' => 'carlos@correo.co', 'policy_version' => DataPolicy::VERSION])
        ->and(AuditLabels::action('privacy.data_authorized'))->toBe('Autorizó el tratamiento de sus datos personales');
});

it('Sin la autorización no se activa la cuenta: a veedor nor an Administrador, and the invitation stays pending', function () {
    [$veedor, $token] = invitedMember(fn () => (new InviteObserver)->handle('carlos@correo.co'));
    [$administrator, $adminToken] = invitedMember(fn () => (new AssignInitialAdministrator)->handle($this->tenant, 'Marta Ospina', 'marta@veeduria.org'));
    tenancy()->end();

    $this->post(POLICY_HOST."/set-password/{$veedor->id}", ['token' => $token, 'password' => 'Veeduria#2026', 'password_confirmation' => 'Veeduria#2026', 'declaration' => true])
        ->assertSessionHasErrors(['data_authorization' => AUTHORIZATION_REQUIRED]);
    $this->post(POLICY_HOST."/set-password/{$administrator->id}", ['token' => $adminToken, 'password' => 'Veeduria#2026', 'password_confirmation' => 'Veeduria#2026'])
        ->assertSessionHasErrors(['data_authorization' => AUTHORIZATION_REQUIRED]);
    $this->assertGuest('tenant');
    tenancy()->end();

    expect($this->tenant->run(fn () => User::query()->whereNotNull('invitation_token_hash')->count()))->toBe(2)
        ->and(AuditLog::query()->where('action', 'privacy.data_authorized')->exists())->toBeFalse();
});

it('asks for both at once: a veedor who marks neither sees both messages', function () {
    [$veedor, $token] = invitedMember(fn () => (new InviteObserver)->handle('carlos@correo.co'));
    tenancy()->end();

    $this->post(POLICY_HOST."/set-password/{$veedor->id}", ['token' => $token, 'password' => 'Veeduria#2026', 'password_confirmation' => 'Veeduria#2026'])
        ->assertSessionHasErrors(['data_authorization' => AUTHORIZATION_REQUIRED, 'declaration' => 'Para ser veedor, declare que no está en ninguno de estos casos.']);
});
