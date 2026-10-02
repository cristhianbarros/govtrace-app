<?php

use App\Application\Auth\AuthenticateUser;
use App\Application\Platform\SuperAdministrators;
use App\Application\Sealing\SuperAdminAlerts;
use App\Domain\Audit\AuditLog;
use App\Domain\Auth\Exceptions\AuthenticationRejected;
use App\Domain\Organization\InvitationToken;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Platform\Notifications\OnlyOneSuperAdministrator;
use App\Domain\Sealing\Notifications\SponsorBalanceLow;
use App\Models\User as SuperAdmin;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 46a — features/US-063-USR.feature: varios Super Administradores,
 * y nunca ninguno. Hoy solo se crean desde la consola (make admin) y no se
 * pueden desactivar; desde esta iteración, uno invita a otro desde el panel,
 * lo desactiva si se fue y lo reactiva si vuelve, sin dejar nunca la
 * plataforma sin uno activo — ni aunque dos se desactiven el uno al otro al
 * mismo tiempo (una carrera real, con otro proceso, como FirstTouchRaceTest).
 */

const CENTRAL = 'http://govtrace.localhost';
const STRONG_PASSWORD = 'Luis#2026clave';
const NOT_YOURSELF = 'No puede desactivar su propia cuenta. Pídale a otro Super Administrador que lo haga.';
const ONLY_ACTIVE_SUPER_ADMIN = 'No se puede desactivar al único Super Administrador activo. Invite a otro y espere a que active su cuenta.';
const ONE_LEFT = 'Solo hay un Super Administrador activo. Si pierde el acceso, nadie podrá dar de alta veedurías ni atender las alertas. Invite a otro.';

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();
    SuperAdmin::query()->delete();
    $this->ana = SuperAdmin::factory()->create(['name' => 'Ana Directora', 'email' => 'ana@govtrace.org']);
});

afterEach(function () {
    SuperAdmin::query()->delete();
    DB::table('audit_logs')->where('action', 'like', 'super_admin.%')->delete();
    DB::table('audit_logs')->whereNull('organization_id')->where('action', 'privacy.data_authorized')->delete();
});

function inTheGlobalPanelAs(SuperAdmin $superAdmin, string $method, string $path, array $data = []): TestResponse
{
    test()->flushSession();

    return test()->actingAs($superAdmin, 'web')->json($method, CENTRAL.$path, $data);
}

/** @return list<array<string, mixed>> the Super Administrators as the panel lists them to $viewer */
function superAdministratorsSeenBy(SuperAdmin $viewer): array
{
    return inTheGlobalPanelAs($viewer, 'GET', '/admin/super-administrators/data')->assertOk()->json('data');
}

function activeSuperAdmin(string $name, string $email): SuperAdmin
{
    return SuperAdmin::factory()->create(['name' => $name, 'email' => $email, 'password' => STRONG_PASSWORD]);
}

/** An invitation as the panel sends it, and the plain token of its link. */
function invitedSuperAdmin(string $name, string $email): array
{
    inTheGlobalPanelAs(test()->ana, 'POST', '/admin/super-administrators', ['name' => $name, 'email' => $email])->assertCreated();
    $invited = SuperAdmin::query()->where('email', $email)->firstOrFail();

    $url = null;
    Notification::assertSentTo($invited, WelcomeNotification::class, function (WelcomeNotification $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return [$invited, $query['token'], $url];
}

function signsIn(string $email, string $password = STRONG_PASSWORD): ?string
{
    try {
        (new AuthenticateUser)->handle('web', $email, $password);
        auth('web')->logout();

        return null;
    } catch (AuthenticationRejected $rejected) {
        return $rejected->getMessage();
    }
}

it('Un Super Administrador invita a otro: the invitation goes out, it shows as pending, and it is audited', function () {
    [$luis, , $url] = invitedSuperAdmin('Luis Gómez', 'luis@govtrace.org');

    expect($url)->toStartWith(CENTRAL."/set-password/{$luis->id}?token=")
        ->and(collect(superAdministratorsSeenBy($this->ana))->firstWhere('email', 'luis@govtrace.org'))
        ->toMatchArray(['name' => 'Luis Gómez', 'status' => 'pending', 'label' => 'Invitación pendiente', 'is_me' => false])
        ->and(AuditLog::query()->where('action', 'super_admin.invited')->first())
        ->toMatchArray(['actor_type' => 'super_admin', 'actor_name' => 'Ana Directora', 'organization_id' => null])
        ->and(signsIn('luis@govtrace.org'))->not->toBeNull();
});

it('El invitado activa su cuenta y entra al panel global: with his name, his password and the authorization of his data', function () {
    [$luis, $token] = invitedSuperAdmin('Luis Gómez', 'luis@govtrace.org');

    $this->withoutVite()->get(CENTRAL."/set-password/{$luis->id}?token={$token}")
        ->assertInertia(fn (Assert $page) => $page->component('Auth/SetPassword')->where('valid', true)->where('declaration', false)->where('action', "/set-password/{$luis->id}")
            ->where('nameHint', 'Lo ven los demás Super Administradores, y queda en el registro de auditoría.'));

    $this->post(CENTRAL."/set-password/{$luis->id}", ['token' => $token, 'name' => 'Luis Gómez Ruiz', 'password' => STRONG_PASSWORD, 'password_confirmation' => STRONG_PASSWORD, 'data_authorization' => true])
        ->assertRedirect(CENTRAL.'/dashboard');

    $luis->refresh();
    expect(auth('web')->id())->toBe($luis->id)
        ->and($luis->name)->toBe('Luis Gómez Ruiz')
        ->and($luis->invitation_token_hash)->toBeNull()
        ->and($luis->data_authorized_at)->not->toBeNull()
        ->and(collect(superAdministratorsSeenBy($this->ana))->firstWhere('id', $luis->id)['status'])->toBe('active')
        ->and(AuditLog::query()->where('action', 'super_admin.activated')->where('actor_id', (string) $luis->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->whereNull('organization_id')->where('action', 'privacy.data_authorized')->exists())->toBeTrue();
});

it('does not activate the account without the authorization of the data, nor with a wrong or expired link', function () {
    [$luis, $token] = invitedSuperAdmin('Luis Gómez', 'luis@govtrace.org');
    $data = ['token' => $token, 'name' => 'Luis Gómez', 'password' => STRONG_PASSWORD, 'password_confirmation' => STRONG_PASSWORD];

    $this->postJson(CENTRAL."/set-password/{$luis->id}", $data)->assertJsonValidationErrors('data_authorization');
    $this->postJson(CENTRAL."/set-password/{$luis->id}", [...$data, 'token' => 'otro', 'data_authorization' => true])->assertJsonValidationErrors('token');

    $luis->forceFill(['invitation_expires_at' => now()->subMinute()])->save();
    $this->withoutVite()->get(CENTRAL."/set-password/{$luis->id}?token={$token}")
        ->assertInertia(fn (Assert $page) => $page->where('valid', false));
    expect($luis->fresh()->invitation_token_hash)->not->toBeNull();
});

it('Un Super Administrador desactiva a otro que se fue: he can no longer sign in, and his open session ends on his next request', function () {
    $luis = activeSuperAdmin('Luis Gómez', 'luis@govtrace.org');

    inTheGlobalPanelAs($this->ana, 'POST', "/admin/super-administrators/{$luis->id}/deactivate")->assertOk();

    expect(signsIn('luis@govtrace.org'))->toBe('Su cuenta se encuentra desactivada. Comuníquese con otro Super Administrador de GovTrace.')
        ->and(AuditLog::query()->where('action', 'super_admin.deactivated')->first())
        ->toMatchArray(['actor_name' => 'Ana Directora'])
        ->and(AuditLog::query()->where('action', 'super_admin.deactivated')->first()->before)->toMatchArray(['email' => 'luis@govtrace.org', 'is_active' => true]);

    inTheGlobalPanelAs($luis, 'GET', '/admin/organizations/data')
        ->assertForbidden()
        ->assertJson(['message' => 'Su cuenta de Super Administrador fue desactivada.']);
});

it('Un Super Administrador reactiva a otro: he can sign in again', function () {
    $luis = activeSuperAdmin('Luis Gómez', 'luis@govtrace.org');
    inTheGlobalPanelAs($this->ana, 'POST', "/admin/super-administrators/{$luis->id}/deactivate")->assertOk();

    inTheGlobalPanelAs($this->ana, 'POST', "/admin/super-administrators/{$luis->id}/reactivate")->assertOk();

    expect(signsIn('luis@govtrace.org'))->toBeNull()
        ->and(AuditLog::query()->where('action', 'super_admin.reactivated')->exists())->toBeTrue();
});

it('Reenviar y revocar una invitación pendiente de Super Administrador: a new link replaces the old one, and revoking leaves none', function () {
    [$luis, $firstToken] = invitedSuperAdmin('Luis Gómez', 'luis@govtrace.org');

    inTheGlobalPanelAs($this->ana, 'POST', "/admin/super-administrators/{$luis->id}/invitation/resend")->assertOk();

    $luis->refresh();
    expect($luis->invitation_token_hash)->not->toBe(InvitationToken::hashOf($firstToken));
    Notification::assertSentToTimes($luis, WelcomeNotification::class, 2);

    inTheGlobalPanelAs($this->ana, 'POST', "/admin/super-administrators/{$luis->id}/invitation/revoke")->assertOk();

    expect(SuperAdmin::query()->where('email', 'luis@govtrace.org')->exists())->toBeFalse()
        ->and(AuditLog::query()->whereIn('action', ['super_admin.invitation_resent', 'super_admin.invitation_revoked'])->count())->toBe(2);
});

it('does not resend nor revoke the invitation of an account already active', function (string $action) {
    $luis = activeSuperAdmin('Luis Gómez', 'luis@govtrace.org');

    inTheGlobalPanelAs($this->ana, 'POST', "/admin/super-administrators/{$luis->id}/invitation/{$action}")->assertNotFound();
})->with(['resend', 'revoke']);

it('Un Super Administrador no se desactiva a sí mismo', function () {
    activeSuperAdmin('Luis Gómez', 'luis@govtrace.org');

    inTheGlobalPanelAs($this->ana, 'POST', "/admin/super-administrators/{$this->ana->id}/deactivate")
        ->assertUnprocessable()
        ->assertJson(['message' => NOT_YOURSELF]);

    expect($this->ana->fresh()->is_active)->toBeTrue()
        ->and(collect(superAdministratorsSeenBy($this->ana))->firstWhere('id', $this->ana->id)['is_me'])->toBeTrue();
});

/*
 * La carrera: Ana desactiva a Luis con su transacción abierta; Luis, que
 * todavía tenía la sesión, intenta desactivar a Ana desde otro proceso
 * (tests/Support/deactivate_super_admin_in_parallel.php) y TIENE que quedarse
 * esperando el bloqueo. Solo cuando Postgres lo muestra esperando, Ana confirma.
 */
it('Nunca quedan cero Super Administradores activos: of two who deactivate each other at the same time, only one deactivation holds', function () {
    $luis = activeSuperAdmin('Luis Gómez', 'luis@govtrace.org');
    invitedSuperAdmin('Marta Ruiz', 'marta@govtrace.org');
    $probe = config('database.default').'_probe';
    config(["database.connections.{$probe}" => config('database.connections.'.config('database.default'))]);
    $database = DB::connection()->getDatabaseName();

    DB::beginTransaction();
    (new SuperAdministrators)->deactivate($this->ana, $luis->id);

    $child = proc_open(
        [PHP_BINARY, base_path('tests/Support/deactivate_super_admin_in_parallel.php'), (string) $luis->id, (string) $this->ana->id],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        base_path(),
    );
    $waiting = false;
    $deadline = microtime(true) + 30;
    while (! $waiting && microtime(true) < $deadline) {
        usleep(100_000);
        $waiting = (int) DB::connection($probe)->selectOne("select count(*) as waiting from pg_stat_activity where datname = ? and wait_event_type = 'Lock'", [$database])->waiting > 0;
    }
    DB::commit();

    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($child);
    $result = json_decode($output, true) ?? ['status' => 'crashed', 'output' => $output];

    expect($waiting)->toBeTrue()
        ->and($result)->toBe(['status' => 'refused', 'message' => ONLY_ACTIVE_SUPER_ADMIN])
        ->and($this->ana->fresh()->is_active)->toBeTrue()
        ->and($luis->fresh()->is_active)->toBeFalse()
        ->and(SuperAdmin::query()->active()->count())->toBe(1);
});

it('El panel avisa cuando queda un solo Super Administrador activo: it shares how many are active with every screen', function () {
    invitedSuperAdmin('Luis Gómez', 'luis@govtrace.org');

    $this->flushSession();
    $this->withoutVite()->actingAs($this->ana, 'web')->get(CENTRAL.'/admin/organizations')
        ->assertInertia(fn (Assert $page) => $page->where('superAdministratorsActive', 1));

    activeSuperAdmin('Marta Ruiz', 'marta@govtrace.org');
    $this->flushSession();
    $this->withoutVite()->actingAs($this->ana, 'web')->get(CENTRAL.'/admin/organizations')
        ->assertInertia(fn (Assert $page) => $page->where('superAdministratorsActive', 2));
});

it('Al quedar un solo Super Administrador activo llega una alerta: to the active ones and the webhook', function () {
    config(['services.alerts.webhook_url' => 'https://alertas.example/hook']);
    $luis = activeSuperAdmin('Luis Gómez', 'luis@govtrace.org');

    inTheGlobalPanelAs($this->ana, 'POST', "/admin/super-administrators/{$luis->id}/deactivate")->assertOk();

    Notification::assertSentTo($this->ana, OnlyOneSuperAdministrator::class, fn (OnlyOneSuperAdministrator $alert) => $alert->subject() === 'GovTrace: queda un solo Super Administrador activo'
        && str_contains($alert->message(), ONE_LEFT));
    Notification::assertNotSentTo($luis, OnlyOneSuperAdministrator::class);
    Notification::assertSentOnDemand(OnlyOneSuperAdministrator::class, fn ($alert, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes !== []);
});

it('sends no alert while two or more remain active', function () {
    $luis = activeSuperAdmin('Luis Gómez', 'luis@govtrace.org');
    activeSuperAdmin('Marta Ruiz', 'marta@govtrace.org');

    inTheGlobalPanelAs($this->ana, 'POST', "/admin/super-administrators/{$luis->id}/deactivate")->assertOk();

    Notification::assertNothingSentTo($this->ana, OnlyOneSuperAdministrator::class);
});

it('Las alertas les llegan solo a los Super Administradores activos: not to a deactivated one, nor to a pending invitation', function () {
    $luis = activeSuperAdmin('Luis Gómez', 'luis@govtrace.org');
    $luis->forceFill(['is_active' => false])->save();
    [$marta] = invitedSuperAdmin('Marta Ruiz', 'marta@govtrace.org');

    SuperAdminAlerts::send(new SponsorBalanceLow('GSPONSOR', 400_000_000));

    Notification::assertSentTo($this->ana, SponsorBalanceLow::class);
    Notification::assertNotSentTo($luis, SponsorBalanceLow::class);
    Notification::assertNotSentTo($marta, SponsorBalanceLow::class);
});

it('El correo de un Super Administrador nuevo no puede estar registrado', function () {
    activeSuperAdmin('Luis Gómez', 'luis@govtrace.org');

    inTheGlobalPanelAs($this->ana, 'POST', '/admin/super-administrators', ['name' => 'Otro Luis', 'email' => 'LUIS@govtrace.org'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'El correo electrónico ya se encuentra registrado en el sistema.']);
});

it('Solo un Super Administrador gestiona Super Administradores: without a session in the global panel it is rejected', function () {
    $luis = activeSuperAdmin('Luis Gómez', 'luis@govtrace.org');

    $this->postJson(CENTRAL.'/admin/super-administrators', ['name' => 'Intruso', 'email' => 'intruso@correo.co'])->assertUnauthorized();
    $this->postJson(CENTRAL."/admin/super-administrators/{$luis->id}/deactivate")->assertUnauthorized();
    $this->getJson(CENTRAL.'/admin/super-administrators/data')->assertUnauthorized();

    expect($luis->fresh()->is_active)->toBeTrue();
});

it('still creates an active Super Administrador from the console, the way to recover the access', function () {
    $this->artisan('admin:create', ['email' => 'rescate@govtrace.org', '--name' => 'Rescate'])
        ->expectsQuestion('Contraseña (vacío para generar una)', 'Rescate#2026clave')
        ->assertSuccessful();

    expect(SuperAdmin::query()->active()->where('email', 'rescate@govtrace.org')->exists())->toBeTrue();
});
