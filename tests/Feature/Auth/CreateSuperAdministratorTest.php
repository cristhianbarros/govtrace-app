<?php

use App\Application\Auth\CreateSuperAdministrator;
use App\Domain\Audit\AuditLog;
use App\Domain\Auth\Rules\StrongPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/*
 * Iteración 38 — el primer Super Administrador. En una base nueva no hay
 * ninguno y ninguna pantalla lo crea (crea organizaciones, no a sí mismo):
 * make admin EMAIL=… lo deja en la base central, con la contraseña que se
 * escribe (sin eco, nunca en la línea de comandos) o una generada que se
 * muestra una sola vez.
 */

uses(RefreshDatabase::class);

const CENTRAL_LOGIN = 'http://govtrace.localhost:8080/login';

it('creates the Super Administrator with the password given, and they can log in', function () {
    (new CreateSuperAdministrator)->handle('Ana Directora', 'ana@govtrace.test', 'Cambiame#2026');

    $admin = User::query()->where('email', 'ana@govtrace.test')->firstOrFail();
    expect($admin->name)->toBe('Ana Directora')
        ->and(Hash::check('Cambiame#2026', $admin->password))->toBeTrue();

    $this->post(CENTRAL_LOGIN, ['email' => 'ana@govtrace.test', 'password' => 'Cambiame#2026'])
        ->assertRedirect();
    $this->assertAuthenticatedAs($admin, 'web');
});

it('generates a strong password when none is given, and returns it once', function () {
    $created = (new CreateSuperAdministrator)->handle('Ana Directora', 'ana@govtrace.test', null);

    expect($created->generatedPassword)->not->toBeNull()
        ->and(Validator::make(['p' => $created->generatedPassword], ['p' => [new StrongPassword]])->passes())->toBeTrue()
        ->and(Hash::check($created->generatedPassword, $created->user->password))->toBeTrue();
});

it('does not generate anything when a password is given', function () {
    $created = (new CreateSuperAdministrator)->handle('Ana Directora', 'ana@govtrace.test', 'Cambiame#2026');

    expect($created->generatedPassword)->toBeNull();
});

it('keeps only the hash of the password, and the audit log never sees it', function () {
    (new CreateSuperAdministrator)->handle('Ana Directora', 'ana@govtrace.test', 'Cambiame#2026');

    $stored = User::query()->where('email', 'ana@govtrace.test')->value('password');
    $entry = AuditLog::query()->where('action', 'super_admin.created')->firstOrFail();

    expect($stored)->not->toContain('Cambiame#2026')
        ->and($entry->actor_type)->toBe('system')
        ->and($entry->after)->toBe(['email' => 'ana@govtrace.test', 'name' => 'Ana Directora'])
        ->and(json_encode($entry->toArray()))->not->toContain('Cambiame');
});

it('refuses a weak password, a malformed email and one already in use', function (string $email, string $password, string $message) {
    User::factory()->create(['email' => 'ya@govtrace.test']);

    expect(fn () => (new CreateSuperAdministrator)->handle('Ana', $email, $password))
        ->toThrow(InvalidArgumentException::class, $message);

    expect(User::query()->where('email', $email)->count())->toBe($email === 'ya@govtrace.test' ? 1 : 0);
})->with([
    'weak password' => ['ana@govtrace.test', 'corta', 'La contraseña debe tener al menos 8 caracteres'],
    'malformed email' => ['ana-sin-arroba', 'Cambiame#2026', 'no es un correo válido'],
    'email already in use' => ['ya@govtrace.test', 'Cambiame#2026', 'Ya existe un Super Administrador con ese correo'],
]);

it('make admin: asks for the password without echoing it', function () {
    $this->artisan('admin:create', ['email' => 'ana@govtrace.test', '--name' => 'Ana Directora'])
        ->expectsQuestion('Contraseña (vacío para generar una)', 'Cambiame#2026')
        ->expectsOutputToContain('Super Administrador creado: ana@govtrace.test')
        ->assertSuccessful();

    expect(Hash::check('Cambiame#2026', User::query()->where('email', 'ana@govtrace.test')->value('password')))->toBeTrue();
});

it('make admin: with no password typed, generates one and shows it once', function () {
    $this->artisan('admin:create', ['email' => 'ana@govtrace.test'])
        ->expectsQuestion('Contraseña (vacío para generar una)', '')
        ->expectsOutputToContain('Contraseña generada (se muestra una sola vez)')
        ->assertSuccessful();
});

it('make admin: without a terminal to ask, generates one', function () {
    $this->artisan('admin:create', ['email' => 'ana@govtrace.test', '--no-interaction' => true])
        ->expectsOutputToContain('Contraseña generada (se muestra una sola vez)')
        ->assertSuccessful();
});

it('make admin: says why it failed, and creates nothing', function () {
    User::factory()->create(['email' => 'ana@govtrace.test']);

    $this->artisan('admin:create', ['email' => 'ana@govtrace.test', '--no-interaction' => true])
        ->expectsOutputToContain('Ya existe un Super Administrador con ese correo')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});
