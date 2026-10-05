<?php

use App\Application\Auth\SuperAdminTwoFactor;
use App\Application\Organization\RegisterOrganization;
use App\Application\Platform\SuperAdministrators;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FA\Google2FA;

/*
 * Iteración 46g — US-065-SEC, R-SEC-09: la verificación en dos pasos del
 * Super Administrador (TOTP, RFC 6238), inactiva hasta que el operador la
 * activa en el servidor (SUPER_ADMIN_TWO_FACTOR). Cada test la enciende con
 * config(); los códigos se calculan con la misma librería que la app, en la
 * ventana de 30 segundos de ahora y sus vecinas.
 */

const TFA_HOST = 'http://govtrace.localhost';
const TFA_PASSWORD = 'Ana#2026clave';
const TFA_INVALID = 'El código no es válido o ya venció. Escriba el de 6 dígitos que muestra su app ahora.';
const TFA_RECOVERY_INVALID = 'Ese código de recuperación no es válido o ya se usó.';
const TFA_EXPIRED = 'Pasaron más de 10 minutos. Vuelva a escribir su contraseña.';
const TFA_SESSION_CLOSED = 'Por seguridad, vuelva a entrar: ahora el panel global pide un código de su app autenticadora.';
const TFA_LOCKED = 'Demasiados intentos fallidos. Tu cuenta ha sido bloqueada temporalmente durante 15 minutos.';

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();
    SuperAdmin::query()->delete();
    $this->ana = SuperAdmin::factory()->create(['name' => 'Ana Directora', 'email' => 'ana@govtrace.org', 'password' => TFA_PASSWORD]);
    config(['auth.super_admin_two_factor' => true]);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    SuperAdmin::query()->delete();
    DB::table('audit_logs')->where('action', 'like', 'super_admin.%')->delete();
    DB::table('audit_logs')->where('action', 'privacy.data_authorized')->delete();
});

function tfaPassword(string $email = 'ana@govtrace.org', string $password = TFA_PASSWORD): TestResponse
{
    return test()->post(TFA_HOST.'/login', ['email' => $email, 'password' => $password]);
}

/** The code the app shows, $steps windows of 30 s from now. */
function tfaCode(string $secret, int $steps = 0): string
{
    return (new Google2FA)->oathTotp($secret, intdiv(time(), 30) + $steps);
}

/** Ana with her app already configured: its secret and her recovery codes. Confirming used the code of now. */
function tfaEnrolled(SuperAdmin $superAdmin): array
{
    $secret = (new Google2FA)->generateSecretKey(32);
    $codes = (new SuperAdminTwoFactor)->confirm($superAdmin, $secret, tfaCode($secret));

    return [$secret, $codes];
}

it('Mientras la verificación en dos pasos está inactiva, el Super Administrador entra solo con su contraseña', function () {
    config(['auth.super_admin_two_factor' => false]);

    tfaPassword()->assertRedirect(TFA_HOST.'/dashboard');

    $this->assertAuthenticatedAs($this->ana, 'web');
    $this->get(TFA_HOST.'/two-factor')->assertNotFound();
});

it('La primera vez, el Super Administrador configura su app autenticadora: a QR code and its key, a code to confirm, and 8 recovery codes once', function () {
    tfaPassword()->assertRedirect(TFA_HOST.'/two-factor');
    $this->assertGuest('web');

    $secret = null;
    $this->withoutVite()->get(TFA_HOST.'/two-factor')->assertOk()->assertInertia(function (AssertableInertia $page) use (&$secret) {
        $page->component('Auth/TwoFactorSetup')->where('email', 'ana@govtrace.org')
            ->where('qr', fn (string $qr) => str_starts_with($qr, 'data:image/svg+xml;base64,'));
        $secret = str_replace(' ', '', $page->toArray()['props']['secret']);
    });
    // Recargar la pantalla no cambia la clave que ya se escaneó.
    $this->withoutVite()->get(TFA_HOST.'/two-factor')->assertInertia(fn (AssertableInertia $page) => $page->where('secret', fn (string $again) => str_replace(' ', '', $again) === $secret));

    $this->post(TFA_HOST.'/two-factor/setup', ['code' => '000000'])->assertSessionHasErrors(['code' => TFA_INVALID]);
    $this->assertGuest('web');

    $this->post(TFA_HOST.'/two-factor/setup', ['code' => tfaCode($secret)])->assertRedirect(TFA_HOST.'/two-factor/recovery-codes');
    $this->assertAuthenticatedAs($this->ana, 'web');

    $this->withoutVite()->get(TFA_HOST.'/two-factor/recovery-codes')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Auth/TwoFactorRecoveryCodes')
        ->has('codes', 8)
        ->where('codes.0', fn (string $code) => preg_match('/^[0-9A-HJKMNP-TV-Z]{5}-[0-9A-HJKMNP-TV-Z]{5}$/', $code) === 1));
    // Una sola vez.
    $this->get(TFA_HOST.'/two-factor/recovery-codes')->assertRedirect(TFA_HOST.'/dashboard');

    $stored = DB::table('users')->where('id', $this->ana->id)->first();
    expect($this->ana->fresh()->two_factor_secret)->toBe($secret)
        ->and($stored->two_factor_secret)->not->toContain($secret)
        ->and($stored->two_factor_confirmed_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'super_admin.two_factor_enabled')->where('actor_id', (string) $this->ana->id)->exists())->toBeTrue();
});

it('keeps only the fingerprint of each recovery code', function () {
    [, $codes] = tfaEnrolled($this->ana);

    $stored = DB::table('users')->where('id', $this->ana->id)->value('two_factor_recovery_codes');

    expect($codes)->toHaveCount(8)
        ->and($stored)->not->toContain($codes[0])
        ->and($stored)->toContain(hash('sha256', $codes[0]));
});

it('El Super Administrador entra con su contraseña y el código de su app', function () {
    [$secret] = tfaEnrolled($this->ana);

    tfaPassword()->assertRedirect(TFA_HOST.'/two-factor');
    $this->withoutVite()->get(TFA_HOST.'/two-factor')->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/TwoFactorChallenge'));

    $this->post(TFA_HOST.'/two-factor', ['code' => tfaCode($secret, 1)])->assertRedirect(TFA_HOST.'/dashboard');

    $this->assertAuthenticatedAs($this->ana, 'web');
    $this->withoutVite()->get(TFA_HOST.'/admin/organizations')->assertOk();
});

it('accepts the code of a phone whose clock is 30 seconds behind', function () {
    [$secret] = tfaEnrolled($this->ana);
    // La ventana de "ahora" ya la usó la confirmación: la anterior es la de un reloj atrasado.
    DB::table('users')->where('id', $this->ana->id)->update(['two_factor_last_step' => intdiv(time(), 30) - 2]);

    tfaPassword();

    $this->post(TFA_HOST.'/two-factor', ['code' => tfaCode($secret, -1)])->assertRedirect(TFA_HOST.'/dashboard');
});

it('La contraseña sola no abre el panel', function () {
    tfaEnrolled($this->ana);

    tfaPassword()->assertRedirect(TFA_HOST.'/two-factor');

    $this->get(TFA_HOST.'/admin/organizations')->assertRedirect(TFA_HOST.'/login');
    $this->getJson(TFA_HOST.'/admin/super-administrators/data')->assertUnauthorized();
    $this->assertGuest('web');
});

it('Un código equivocado, vencido o ya usado no deja entrar', function () {
    [$secret] = tfaEnrolled($this->ana);
    tfaPassword();

    $this->post(TFA_HOST.'/two-factor', ['code' => '000000'])->assertSessionHasErrors(['code' => TFA_INVALID]);
    $this->post(TFA_HOST.'/two-factor', ['code' => tfaCode($secret, -3)])->assertSessionHasErrors(['code' => TFA_INVALID]);
    $this->assertGuest('web');

    // El de ahora entra; el mismo, otra vez, ya no.
    $used = tfaCode($secret, 1);
    $this->post(TFA_HOST.'/two-factor', ['code' => $used])->assertRedirect(TFA_HOST.'/dashboard');
    $this->post(TFA_HOST.'/logout');
    tfaPassword();
    $this->post(TFA_HOST.'/two-factor', ['code' => $used])->assertSessionHasErrors(['code' => TFA_INVALID]);
});

it('Un código equivocado, vencido o ya usado no deja entrar: with 5 wrong ones it locks for 15 minutes, even the right code', function () {
    [$secret] = tfaEnrolled($this->ana);
    tfaPassword();

    foreach (range(1, 4) as $attempt) {
        $this->post(TFA_HOST.'/two-factor', ['code' => '000000'])->assertSessionHasErrors(['code' => TFA_INVALID]);
    }
    $this->post(TFA_HOST.'/two-factor', ['code' => '000000'])->assertSessionHasErrors(['code' => TFA_LOCKED]);
    $this->post(TFA_HOST.'/two-factor', ['code' => tfaCode($secret, 1)])->assertSessionHasErrors(['code' => TFA_LOCKED]);
    $this->assertGuest('web');

    $this->travel(16)->minutes();
    tfaPassword();
    $this->post(TFA_HOST.'/two-factor', ['code' => tfaCode($secret, 1)])->assertRedirect(TFA_HOST.'/dashboard');
});

it('Sin su teléfono, entra con un código de recuperación, que sirve una sola vez', function () {
    [, $codes] = tfaEnrolled($this->ana);
    tfaPassword();

    $this->post(TFA_HOST.'/two-factor', ['recovery_code' => strtolower($codes[0])])
        ->assertRedirect(TFA_HOST.'/dashboard')
        ->assertSessionHas('status', 'Entró con un código de recuperación. Le quedan 7.');
    $this->assertAuthenticatedAs($this->ana, 'web');

    $this->post(TFA_HOST.'/logout');
    tfaPassword();
    $this->post(TFA_HOST.'/two-factor', ['recovery_code' => $codes[0]])->assertSessionHasErrors(['recovery_code' => TFA_RECOVERY_INVALID]);
    $this->assertGuest('web');

    expect(AuditLog::query()->where('action', 'super_admin.recovery_code_used')->sole()->after)->toBe(['remaining' => 7]);
});

it('gives 10 minutes to write the code after the password', function () {
    [$secret] = tfaEnrolled($this->ana);
    tfaPassword();

    $this->travel(11)->minutes();

    $this->post(TFA_HOST.'/two-factor', ['code' => tfaCode($secret)])->assertRedirect(TFA_HOST.'/login')->assertSessionHasErrors(['email' => TFA_EXPIRED]);
    $this->get(TFA_HOST.'/two-factor')->assertRedirect(TFA_HOST.'/login');
    $this->assertGuest('web');
});

it('Al activarla, las sesiones abiertas sin el segundo paso se cierran', function () {
    config(['auth.super_admin_two_factor' => false]);
    tfaPassword()->assertRedirect(TFA_HOST.'/dashboard');
    $this->withoutVite()->get(TFA_HOST.'/admin/organizations')->assertOk();

    config(['auth.super_admin_two_factor' => true]);

    $this->get(TFA_HOST.'/admin/organizations')->assertRedirect(TFA_HOST.'/login')->assertSessionHasErrors(['email' => TFA_SESSION_CLOSED]);
    $this->assertGuest('web');
});

it('closes an open session asked from the panel screens too, with the reason', function () {
    config(['auth.super_admin_two_factor' => false]);
    tfaPassword();
    config(['auth.super_admin_two_factor' => true]);

    $this->getJson(TFA_HOST.'/admin/super-administrators/data')->assertUnauthorized()->assertJson(['message' => TFA_SESSION_CLOSED]);
});

it('Un Super Administrador invitado configura su app al activar su cuenta', function () {
    (new SuperAdministrators)->invite($this->ana, 'Luis Gómez', 'luis@govtrace.org');
    $luis = SuperAdmin::query()->where('email', 'luis@govtrace.org')->sole();
    $token = null;
    Notification::assertSentTo($luis, WelcomeNotification::class, function (WelcomeNotification $mail) use (&$token) {
        parse_str((string) parse_url($mail->url, PHP_URL_QUERY), $query);
        $token = $query['token'];

        return true;
    });

    $this->post(TFA_HOST."/set-password/{$luis->public_id}", [
        'token' => $token, 'name' => 'Luis Gómez', 'password' => 'Luis#2026clave', 'password_confirmation' => 'Luis#2026clave', 'data_authorization' => true,
    ])->assertRedirect(TFA_HOST.'/two-factor');

    $this->assertGuest('web');
    $this->withoutVite()->get(TFA_HOST.'/two-factor')->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/TwoFactorSetup')->where('email', 'luis@govtrace.org'));
});

it('Desde la consola del servidor se restablece la verificación de quien perdió su teléfono', function () {
    tfaEnrolled($this->ana);

    $this->artisan('admin:two-factor-reset', ['email' => 'ana@govtrace.org'])
        ->expectsOutput('Verificación en dos pasos restablecida para ana@govtrace.org. La configurará otra vez al entrar.')
        ->assertSuccessful();

    expect($this->ana->fresh()->only(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']))
        ->toBe(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])
        ->and(AuditLog::query()->where('action', 'super_admin.two_factor_reset')->sole()->only(['actor_type', 'actor_name']))
        ->toBe(['actor_type' => 'system', 'actor_name' => 'Consola del servidor']);

    tfaPassword();
    $this->withoutVite()->get(TFA_HOST.'/two-factor')->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/TwoFactorSetup'));
});

it('says so when the console resets one who had not configured it, or someone who is not a Super Administrador', function () {
    $this->artisan('admin:two-factor-reset', ['email' => 'ana@govtrace.org'])
        ->expectsOutput('ana@govtrace.org no tenía configurada la verificación en dos pasos.')
        ->assertSuccessful();
    $this->artisan('admin:two-factor-reset', ['email' => 'nadie@govtrace.org'])
        ->expectsOutput('No hay un Super Administrador con el correo nadie@govtrace.org.')
        ->assertFailed();
});

it('asks nothing of the members of an organization', function () {
    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    reportingMember($tenant, 'carlos@correo.co', Roles::Observer);

    $this->post('http://veeduria-smr.govtrace.localhost/login', ['email' => 'carlos@correo.co', 'password' => 'Veeduria#2026'])
        ->assertRedirect('http://veeduria-smr.govtrace.localhost/reports/new');
});
