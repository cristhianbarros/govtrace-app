<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 4 — Identidad y acceso (specs/PLAN.md). Traduce
 * features/US-031.feature. Sin RefreshDatabase: crear la organización de
 * los tests de tenant ejecuta CREATE DATABASE (igual que it. 3).
 */

beforeEach(function () {
    $this->artisan('migrate');
});

afterEach(function () {
    // Si una llamada anterior a $tenant->run() lanzó una excepción dentro
    // del closure, stancl nunca revierte al contexto central y la conexión
    // al tenant queda abierta -> Postgres rechaza el DROP DATABASE.
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();

    // El Super Administrador vive en la base central, que ningún otro paso
    // limpia: sin esto, correr este archivo dos veces choca con su correo.
    User::query()->where('email', 'root@govtrace.app')->delete();
});

/**
 * @return array{0: Tenant, 1: string}
 */
function registerOrganizationWithMember(string $role, string $email, string $password = 'Veeduria#2026', bool $active = true): array
{
    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    $tenant->run(function () use ($role, $email, $password, $active) {
        $user = OrganizationUser::create([
            'name' => 'Usuario de prueba',
            'email' => $email,
            'password' => $password,
            'is_active' => $active,
        ]);
        $user->assignRole($role);
    });

    return [$tenant, 'veeduria-smr.govtrace.localhost'];
}

it('Inicio de sesión exitoso y redirección por rol: logs in the Super Administrator from the central panel', function () {
    User::factory()->create(['email' => 'root@govtrace.app', 'password' => 'Veeduria#2026']);

    $response = $this->post('http://govtrace.localhost:8080/login', [
        'email' => 'root@govtrace.app',
        'password' => 'Veeduria#2026',
    ]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/dashboard');
    $this->assertAuthenticatedAs(User::where('email', 'root@govtrace.app')->first(), 'web');
});

it('Inicio de sesión exitoso y redirección por rol: logs in the Administrador de Organización from its subdomain', function () {
    [, $domain] = registerOrganizationWithMember(Roles::Administrator->value, 'ana.perez@veeduria-smr.org');

    $this->post("http://{$domain}/login", [
        'email' => 'ana.perez@veeduria-smr.org',
        'password' => 'Veeduria#2026',
    ])->assertRedirect("http://{$domain}/organization/dashboard");
});

it('Inicio de sesión exitoso y redirección por rol: logs in the Veedor de Campo from its subdomain', function () {
    [, $domain] = registerOrganizationWithMember(Roles::Observer->value, 'carlos@correo.co');

    $this->post("http://{$domain}/login", [
        'email' => 'carlos@correo.co',
        'password' => 'Veeduria#2026',
    ])->assertRedirect("http://{$domain}/reports/new"); // "Nuevo Reporte", hasta "Mis Reportes" (it. 28)
});

it('rejects incorrect credentials', function () {
    [, $domain] = registerOrganizationWithMember(Roles::Observer->value, 'carlos@correo.co');

    $response = $this->from("http://{$domain}/login")->post("http://{$domain}/login", [
        'email' => 'carlos@correo.co',
        'password' => 'Otra#2026',
    ]);

    $response->assertSessionHasErrors(['email' => 'Credenciales incorrectas. Verifique su correo electrónico y contraseña.']);
    $this->assertGuest('tenant');
});

it('rejects a malformed login form', function (string $email, string $password) {
    [, $domain] = registerOrganizationWithMember(Roles::Observer->value, 'carlos@correo.co');

    $this->post("http://{$domain}/login", ['email' => $email, 'password' => $password])
        ->assertSessionHasErrors();
    $this->assertGuest('tenant');
})->with([
    'correo sin dominio' => ['carlos@', 'Veeduria#2026'],
    'contraseña vacía' => ['carlos@correo.co', ''],
]);

it('locks the account for 15 minutes after 5 failed attempts', function () {
    [, $domain] = registerOrganizationWithMember(Roles::Observer->value, 'carlos@correo.co');

    for ($i = 0; $i < 4; $i++) {
        $this->post("http://{$domain}/login", ['email' => 'carlos@correo.co', 'password' => 'mala'])
            ->assertSessionHasErrors(['email' => 'Credenciales incorrectas. Verifique su correo electrónico y contraseña.']);
    }

    // Fifth failed attempt: itself must already show the lockout message.
    $this->post("http://{$domain}/login", ['email' => 'carlos@correo.co', 'password' => 'mala'])
        ->assertSessionHasErrors(['email' => 'Demasiados intentos fallidos. Tu cuenta ha sido bloqueada temporalmente durante 15 minutos.']);

    // Even with the correct password, still locked.
    $this->post("http://{$domain}/login", ['email' => 'carlos@correo.co', 'password' => 'Veeduria#2026'])
        ->assertSessionHasErrors(['email' => 'Demasiados intentos fallidos. Tu cuenta ha sido bloqueada temporalmente durante 15 minutos.']);

    $this->assertGuest('tenant');
});

it('rejects a deactivated account even with the correct password', function () {
    [, $domain] = registerOrganizationWithMember(Roles::Observer->value, 'carlos@correo.co', active: false);

    $response = $this->post("http://{$domain}/login", [
        'email' => 'carlos@correo.co',
        'password' => 'Veeduria#2026',
    ]);

    $response->assertSessionHasErrors(['email' => 'Su cuenta se encuentra desactivada. Comuníquese con el administrador de su organización.']);
    $this->assertGuest('tenant');
});

it('El Verificador Público no necesita iniciar sesión: lets a public visitor reach tenant routes without logging in', function () {
    [, $domain] = registerOrganizationWithMember(Roles::Observer->value, 'carlos@correo.co');

    $this->get("http://{$domain}/")->assertOk();
    $this->assertGuest('tenant');
});

/*
 * It. 40b — V1 de docs/mapa-funcional.md: cerrar sesión desde cualquier panel.
 * La salida del veedor la prueba OfflineReportsTest; la ruta central no existía.
 */

it('Cerrar sesión desde cualquier panel: the Super Administrador logs out of the global panel, which then asks to log in again', function () {
    $superAdministrator = User::factory()->create(['email' => 'root@govtrace.app', 'password' => 'Veeduria#2026']);

    $this->actingAs($superAdministrator, 'web')->postJson('http://govtrace.localhost:8080/logout')->assertNoContent();

    $this->assertGuest('web');
    $this->get('http://govtrace.localhost:8080/admin/organizations')->assertRedirect();
});

it('Cerrar sesión desde cualquier panel: the Administrador de Organización logs out of the panel of the organization', function () {
    [$tenant, $domain] = registerOrganizationWithMember(Roles::Administrator->value, 'ana.perez@veeduria-smr.org');
    $administrator = $tenant->run(fn () => OrganizationUser::query()->where('email', 'ana.perez@veeduria-smr.org')->firstOrFail());

    $this->actingAs($administrator, 'tenant')->postJson("http://{$domain}/logout")->assertNoContent();

    $this->assertGuest('tenant');
});

it('shares who is logged in with every screen of a panel, so the header can name them', function () {
    [$tenant, $domain] = registerOrganizationWithMember(Roles::Administrator->value, 'ana.perez@veeduria-smr.org');
    $administrator = $tenant->run(fn () => OrganizationUser::query()->where('email', 'ana.perez@veeduria-smr.org')->firstOrFail());

    $this->withoutVite()->actingAs($administrator, 'tenant')
        ->get("http://{$domain}/admin/inbox")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('account', ['name' => 'Usuario de prueba', 'email' => 'ana.perez@veeduria-smr.org', 'role' => 'Administrador de Organización']));
});

it('shares the Super Administrador too, and nobody on a public screen', function () {
    $superAdministrator = User::factory()->create(['name' => 'Raíz', 'email' => 'root@govtrace.app', 'password' => 'Veeduria#2026']);
    registerOrganizationWithMember(Roles::Observer->value, 'carlos@correo.co');

    $this->withoutVite()->actingAs($superAdministrator, 'web')->get('http://govtrace.localhost:8080/admin/organizations')
        ->assertInertia(fn (Assert $page) => $page->where('account', ['name' => 'Raíz', 'email' => 'root@govtrace.app', 'role' => 'Super Administrador']));
    $this->withoutVite()->get('http://veeduria-smr.govtrace.localhost/')
        ->assertInertia(fn (Assert $page) => $page->where('account', null));
});
