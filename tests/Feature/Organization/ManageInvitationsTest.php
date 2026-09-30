<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Audit\AuditLog;
use App\Domain\Configuration\Parameters;
use App\Domain\Configuration\ParameterValue;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 33 — US-040-USR (features/US-040-USR.feature, 2 casos): el
 * Administrador de Organización reenvía una invitación pendiente — un
 * enlace nuevo, con la vigencia configurada (US-038-CFG); el anterior deja
 * de valer — o la revoca: su enlace se comporta como uno vencido (US-030).
 * Las dos quedan en el log de auditoría (R-AUD-04).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const INVITATION_INVALID = 'El enlace de invitación ha expirado o no es válido. Solicite una nueva invitación al administrador.';

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();
    Carbon::setTestNow('2026-09-29 12:00:00');
    $this->lastParameterVersion = (int) ParameterValue::query()->max('id');

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);

    // Antecedentes: "carlos@correo.co" tiene una invitación pendiente.
    asInvitingAdministrator('POST', '/observers/invite', ['email' => 'carlos@correo.co'])->assertCreated();
    $this->invited = $this->tenant->run(fn () => OrganizationUser::query()->where('email', 'carlos@correo.co')->sole());
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('audit_logs')->delete();
    ParameterValue::query()->where('id', '>', $this->lastParameterVersion)->delete();
    Carbon::setTestNow();
});

function asInvitingAdministrator(string $method, string $path, array $data = []): TestResponse
{
    return test()->actingAs(test()->administrator, 'tenant')->json($method, "http://veeduria-smr.govtrace.localhost{$path}", $data);
}

/** @return list<string> the links sent to the veedor, oldest first */
function invitationLinks(): array
{
    return Notification::sent(test()->invited, WelcomeNotification::class)->map(fn (WelcomeNotification $mail) => $mail->url)->values()->all();
}

/** The set-password screen that opens a link (US-030), as the veedor sees it, without a session. */
function openInvitation(string $url): TestResponse
{
    return publicGet(parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY));
}

function setPasswordWith(string $url): TestResponse
{
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    test()->flushSession();

    return test()->postJson('http://veeduria-smr.govtrace.localhost'.parse_url($url, PHP_URL_PATH), [
        'token' => $query['token'],
        'password' => 'Veeduria#2026',
        'password_confirmation' => 'Veeduria#2026',
        'declaration' => true, // US-057-LEG: un veedor declara que no tiene impedimentos para serlo
    ]);
}

// US-040-USR ---------------------------------------------------------------------

it('Reenviar una invitación: a new link with the configured validity, and the audit log records it', function () {
    Carbon::setTestNow('2026-09-30 09:00:00');
    Parameters::set('invitation_validity_hours', '24', now()->startOfSecond()); // US-038-CFG, vigente desde ya

    asInvitingAdministrator('POST', "/observers/{$this->invited->id}/invitation/resend")
        ->assertOk()
        ->assertJson(['message' => 'Invitación reenviada a carlos@correo.co. El nuevo enlace vence en 24 horas.']);

    [$first, $second] = invitationLinks();
    expect($second)->not->toBe($first);
    Notification::assertSentTo($this->invited, WelcomeNotification::class, fn (WelcomeNotification $mail) => $mail->url === $second && $mail->validityHours === 24);
    expect($this->tenant->run(fn () => $this->invited->fresh()->invitation_expires_at->toIso8601String()))->toBe('2026-10-01T09:00:00+00:00');

    // El enlace nuevo abre la pantalla; el anterior ya no.
    openInvitation($second)->assertInertia(fn (Assert $page) => $page->component('Auth/SetPassword')->where('valid', true));
    openInvitation($first)->assertInertia(fn (Assert $page) => $page->where('valid', false)->where('message', INVITATION_INVALID));

    $entry = AuditLog::query()->where('action', 'invitation.resent')->sole();
    expect($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_type)->toBe('organization_admin')
        ->and($entry->actor_id)->toBe((string) $this->administrator->id)
        ->and($entry->before)->toBe(['user_id' => $this->invited->id, 'email' => 'carlos@correo.co', 'invitation_expires_at' => '2026-10-01T12:00:00+00:00'])
        ->and($entry->after)->toBe(['user_id' => $this->invited->id, 'email' => 'carlos@correo.co', 'invitation_expires_at' => '2026-10-01T09:00:00+00:00']);
});

it('Un enlace revocado no permite activar la cuenta', function () {
    [$link] = invitationLinks();

    asInvitingAdministrator('POST', "/observers/{$this->invited->id}/invitation/revoke")
        ->assertOk()
        ->assertJson(['message' => 'Invitación revocada. El enlace enviado a carlos@correo.co ya no es válido.']);

    // No puede crear su contraseña, y ve el mensaje de un enlace vencido.
    openInvitation($link)->assertInertia(fn (Assert $page) => $page->component('Auth/SetPassword')->where('valid', false)->where('message', INVITATION_INVALID));
    setPasswordWith($link)->assertUnprocessable()->assertJsonValidationErrors(['token' => INVITATION_INVALID]);
    $this->assertGuest('tenant');

    $entry = AuditLog::query()->where('action', 'invitation.revoked')->sole();
    expect($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_id)->toBe((string) $this->administrator->id)
        ->and($entry->before)->toBe(['user_id' => $this->invited->id, 'email' => 'carlos@correo.co', 'invitation_expires_at' => '2026-10-01T12:00:00+00:00'])
        ->and($entry->after)->toBeNull();
});

// Reglas de US-040-USR ---------------------------------------------------------------

it('resends an invitation that already expired, with a working link again', function () {
    Carbon::setTestNow('2026-10-02 12:00:00'); // 48 h después: vencida

    asInvitingAdministrator('POST', "/observers/{$this->invited->id}/invitation/resend")->assertOk();

    openInvitation(invitationLinks()[1])->assertInertia(fn (Assert $page) => $page->where('valid', true));
    setPasswordWith(invitationLinks()[1])->assertRedirect(); // entra, a su panel
    $this->assertAuthenticatedAs($this->invited->fresh(), 'tenant');
});

it('frees the email of a revoked invitation, so it can be invited again', function () {
    asInvitingAdministrator('POST', "/observers/{$this->invited->id}/invitation/revoke")->assertOk();

    expect(asInvitingAdministrator('GET', '/observers')->json('data'))->toBe([]);
    asInvitingAdministrator('POST', '/observers/invite', ['email' => 'carlos@correo.co'])->assertCreated();
});

it('only resends or revokes a pending invitation: an active veedor has none', function (string $action) {
    $active = reportingMember($this->tenant, 'lucia@correo.co');

    asInvitingAdministrator('POST', "/observers/{$active->id}/invitation/{$action}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status' => 'El veedor no tiene una invitación pendiente.']);

    expect(AuditLog::query()->where('action', 'like', 'invitation.%')->exists())->toBeFalse();
})->with(['resend', 'revoke']);

it('lets only the Administrador resend or revoke', function (string $action) {
    $veedor = reportingMember($this->tenant, 'lucia@correo.co');

    $this->actingAs($veedor, 'tenant')
        ->postJson("http://veeduria-smr.govtrace.localhost/observers/{$this->invited->id}/invitation/{$action}")
        ->assertForbidden();

    expect($this->tenant->run(fn () => $this->invited->fresh()?->invitation_token_hash))->not->toBeNull();
})->with(['resend', 'revoke']);

it('does not find an Administrador among the veedores', function () {
    asInvitingAdministrator('POST', "/observers/{$this->administrator->id}/invitation/revoke")->assertNotFound();
});
