<?php

use App\Application\Organization\AssignInitialAdministrator;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Facades\Notification;

/*
 * Iteración 4 — Identidad y acceso (specs/PLAN.md). Traduce
 * features/US-002.feature. Sin RefreshDatabase, por la misma razón que
 * RegisterOrganizationTest (it. 3): registrar la organización de las
 * Antecedentes ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
});

afterEach(function () {
    // Si una llamada anterior a $tenant->run() lanzó una excepción dentro
    // del closure, stancl nunca revierte al contexto central y la conexión
    // al tenant queda abierta -> Postgres rechaza el DROP DATABASE.
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
});

it('assigns the initial administrator and sends the welcome notification', function () {
    Notification::fake();

    $admin = (new AssignInitialAdministrator)->handle($this->tenant, 'Ana Pérez', 'ana.perez@veeduria-smr.org');

    $this->tenant->run(function () use ($admin) {
        expect(User::query()->whereKey($admin->id)->exists())->toBeTrue()
            ->and($admin->hasRole(Roles::Administrator->value))->toBeTrue();
    });

    Notification::assertSentTo($admin, WelcomeNotification::class, function (WelcomeNotification $notification) {
        return str_contains($notification->url, 'veeduria-smr.govtrace.localhost')
            && str_contains($notification->url, 'set-password');
    });
});

it('rejects an invalid administrator email format and sends no email', function (string $email) {
    Notification::fake();

    $call = fn () => (new AssignInitialAdministrator)->handle($this->tenant, 'Ana Pérez', $email);

    expect($call)->toThrow(OrganizationValidationException::class);
    Notification::assertNothingSent();
})->with([
    'sin dominio' => ['ana.perez'],
    'sin usuario' => ['ana.perez@'],
    'sin nombre antes de @' => ['@veeduria-smr.org'],
    'con espacio' => ['ana perez@veeduria.org'],
]);

it('rejects an administrator email already registered in this organization', function () {
    (new AssignInitialAdministrator)->handle($this->tenant, 'Ana Pérez', 'ana.perez@veeduria-smr.org');

    (new AssignInitialAdministrator)->handle($this->tenant, 'Otra Persona', 'ana.perez@veeduria-smr.org');
})->throws(OrganizationValidationException::class, 'El correo electrónico ya se encuentra registrado en el sistema.');
