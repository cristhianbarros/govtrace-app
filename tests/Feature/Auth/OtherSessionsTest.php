<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\User as OrganizationUser;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/*
 * Iteración 45a — cambiar o restablecer la contraseña cierra las otras
 * sesiones abiertas de esa cuenta (pendiente desde la it. 20). Si alguien
 * entró con la contraseña vieja desde otro equipo, su próxima petición vuelve
 * al inicio de sesión; la app del veedor recibe un 401 en español y guarda
 * sus reportes pendientes. La sesión que la cambió sigue abierta.
 *
 * Sin RefreshDatabase: registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    $this->host = 'http://veeduria-smr.govtrace.localhost';
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
    SuperAdmin::query()->where('email', 'root@govtrace.app')->delete();
});

/** A session opened before the change, on another device: signed in, with the password it signed in with. */
function sessionOpenedBefore(Authenticatable $user, string $guard): array
{
    $session = Auth::guard($guard);

    return [
        $session->getName() => $user->getAuthIdentifier(),
        "password_hash_{$guard}" => $session->hashPasswordForCookie($user->getAuthPassword()),
    ];
}

/** The password changes as the reset link changes it: straight in the database, from elsewhere. */
function passwordChangedElsewhere(Tenant $tenant, int $userId): void
{
    $tenant->run(fn () => OrganizationUser::query()->findOrFail($userId)->forceFill(['password' => 'Otra#Clave2027'])->save());
    Auth::forgetGuards(); // the next request reads the account from the database, as a new request does
}

it('Restablecer la contraseña cierra las otras sesiones: a session opened before goes back to the login on its next request', function () {
    $before = sessionOpenedBefore($this->veedor, 'tenant');
    Auth::forgetGuards();
    $this->withSession($before)->get($this->host.'/my-reports')->assertOk();

    passwordChangedElsewhere($this->tenant, $this->veedor->id);

    $this->withSession($before)->get($this->host.'/my-reports')->assertRedirect($this->host.'/login');
});

it('tells the app of the veedor in Spanish that its session ended, with a 401 that keeps its pending reports', function () {
    $before = sessionOpenedBefore($this->veedor, 'tenant');

    passwordChangedElsewhere($this->tenant, $this->veedor->id);

    $this->withSession($before)->getJson($this->host.'/me/reports')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Su sesión terminó. Vuelva a entrar con su correo y su contraseña.']);
});

it('keeps open the session that changed the password, and closes the others', function () {
    $here = sessionOpenedBefore($this->veedor, 'tenant');
    $elsewhere = $here;
    Auth::forgetGuards();

    $this->withSession($here)->putJson($this->host.'/account/password', [
        'current_password' => 'Veeduria#2026',
        'password' => 'Nueva#Clave2027',
        'password_confirmation' => 'Nueva#Clave2027',
    ])->assertOk();
    Auth::forgetGuards();
    $this->get($this->host.'/my-reports')->assertOk();

    Auth::forgetGuards();
    $this->flushSession();
    $this->withSession($elsewhere)->get($this->host.'/my-reports')->assertRedirect($this->host.'/login');
});

it('closes the other sessions of the Super Administrador too, in the global panel', function () {
    $root = SuperAdmin::factory()->create(['email' => 'root@govtrace.app', 'password' => 'Veeduria#2026']);
    $before = sessionOpenedBefore($root, 'web');

    $root->forceFill(['password' => 'Otra#Clave2027'])->save();
    Auth::forgetGuards();

    $this->withSession($before)->get('http://govtrace.localhost:8080/admin/organizations')->assertRedirect('http://govtrace.localhost:8080/login');
});
