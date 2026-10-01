<?php

use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\User;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 45c — al activar su cuenta, cada persona escribe su nombre (deuda
 * aceptada: el nombre de un veedor invitado era la parte de su correo antes de
 * la @). Su veeduría lo ve en el equipo y en la bandeja; en el sitio público
 * sus reportes siguen con un seudónimo.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    $this->domain = 'http://veeduria-smr.govtrace.localhost';
    [$this->veedor, $this->token] = $this->tenant->run(function () {
        $veedor = (new InviteObserver)->handle('carlos.rojas@correo.co');
        $plain = Str::random(64);
        $veedor->forceFill(['invitation_token_hash' => hash('sha256', $plain)])->save();

        return [$veedor, $plain];
    });
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }
    Tenant::query()->get()->each->delete();
});

function activateWith(array $fields): TestResponse
{
    return test()->post(test()->domain.'/set-password/'.test()->veedor->id, [
        'token' => test()->token,
        'password' => 'Veeduria#2026',
        'password_confirmation' => 'Veeduria#2026',
        'declaration' => true,
        'data_authorization' => true,
        ...$fields,
    ]);
}

it('asks for the name on the activation screen, empty while it is only the start of the email', function () {
    $this->get($this->domain.'/set-password/'.$this->veedor->id.'?token='.$this->token)
        ->assertInertia(fn (Assert $page) => $page->component('Auth/SetPassword')->where('name', ''));
});

it('saves the name the person writes when activating the account', function () {
    activateWith(['name' => '  Carlos Andrés Rojas  '])->assertRedirect();

    expect($this->tenant->run(fn () => User::query()->findOrFail($this->veedor->id)->name))->toBe('Carlos Andrés Rojas');
});

it('refuses a name too short or too long, and says it in Spanish', function (string $name, string $message) {
    activateWith(['name' => $name])->assertSessionHasErrors(['name' => $message]);
})->with([
    'una letra' => ['C', 'Escriba su nombre: al menos 2 letras.'],
    'más de 120' => [str_repeat('a', 121), 'Su nombre puede tener hasta 120 caracteres.'],
]);

it('keeps the name it had when the screen does not send one, as before this iteration', function () {
    activateWith([])->assertRedirect();

    expect($this->tenant->run(fn () => User::query()->findOrFail($this->veedor->id)->name))->toBe('carlos.rojas');
});
