<?php

use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * It. 40c — V11 de docs/mapa-funcional.md: cambiar la contraseña con la
 * sesión abierta, desde "Mi cuenta", en los dos dominios. Pide la actual (una
 * sesión olvidada abierta no basta para cambiarla) y las mismas reglas que al
 * crearla (US-030). Sin RefreshDatabase: la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $this->veedor = $this->tenant->run(function () {
        $user = OrganizationUser::create(['name' => 'Carlos', 'email' => 'carlos@correo.co', 'password' => 'Veeduria#2026']);
        $user->assignRole(Roles::Observer->value);

        return $user;
    });
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
    User::query()->where('email', 'root@govtrace.app')->delete();
});

const TENANT_HOST = 'http://veeduria-smr.govtrace.localhost';

it('Cambiar la contraseña con la sesión abierta: the veedor changes it with the current one, and the new one opens the app', function () {
    $this->actingAs($this->veedor, 'tenant')->putJson(TENANT_HOST.'/account/password', [
        'current_password' => 'Veeduria#2026',
        'password' => 'Nueva#Clave2027',
        'password_confirmation' => 'Nueva#Clave2027',
    ])->assertOk()->assertJson(['message' => 'Su contraseña fue cambiada. La próxima vez entre con la nueva.']);

    $stored = $this->tenant->run(fn () => OrganizationUser::query()->findOrFail($this->veedor->id)->password);
    expect(Hash::check('Nueva#Clave2027', $stored))->toBeTrue()
        ->and(Hash::check('Veeduria#2026', $stored))->toBeFalse();
});

it('Cambiar la contraseña con la sesión abierta: the Super Administrador changes it in the global panel', function () {
    $superAdministrator = User::factory()->create(['email' => 'root@govtrace.app', 'password' => 'Veeduria#2026']);

    $this->actingAs($superAdministrator, 'web')->putJson('http://govtrace.localhost:8080/account/password', [
        'current_password' => 'Veeduria#2026',
        'password' => 'Nueva#Clave2027',
        'password_confirmation' => 'Nueva#Clave2027',
    ])->assertOk();

    expect(Hash::check('Nueva#Clave2027', $superAdministrator->fresh()->password))->toBeTrue();
});

it('does not change it without the right current password', function () {
    $this->actingAs($this->veedor, 'tenant')->putJson(TENANT_HOST.'/account/password', [
        'current_password' => 'Otra#2026',
        'password' => 'Nueva#Clave2027',
        'password_confirmation' => 'Nueva#Clave2027',
    ])->assertUnprocessable()->assertJsonValidationErrors(['current_password' => 'La contraseña actual no es correcta.']);

    expect(Hash::check('Veeduria#2026', $this->tenant->run(fn () => OrganizationUser::query()->findOrFail($this->veedor->id)->password)))->toBeTrue();
});

it('asks the same of the new one as when it was created, and the same twice', function (array $data, string $field) {
    $this->actingAs($this->veedor, 'tenant')
        ->putJson(TENANT_HOST.'/account/password', ['current_password' => 'Veeduria#2026', ...$data])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'débil' => [['password' => 'corta', 'password_confirmation' => 'corta'], 'password'],
    'distinta dos veces' => [['password' => 'Nueva#Clave2027', 'password_confirmation' => 'Nueva#Clave2028'], 'password'],
]);

it('is only for a logged-in person, and has its own screen in both domains', function () {
    $this->putJson(TENANT_HOST.'/account/password', [])->assertUnauthorized();

    $this->withoutVite()->actingAs($this->veedor, 'tenant')->get(TENANT_HOST.'/account/password')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ChangePassword')->where('home', fn (string $home) => str_ends_with($home, '/reports/new')));
});
