<?php

use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Facades\Notification;

/*
 * Iteración 5 — Invitaciones de veedores (specs/PLAN.md). Traduce
 * features/US-005.feature. Sin RefreshDatabase — misma razón que
 * RegisterOrganizationTest (it. 3): registrar la organización de las
 * Antecedentes ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
});

it('invites a veedor with a 48-hour link', function () {
    Notification::fake();

    $veedor = $this->tenant->run(fn () => (new InviteObserver)->handle('carlos@correo.co'));

    $this->tenant->run(function () use ($veedor) {
        expect(now()->diffInHours($veedor->invitation_expires_at))->toBeGreaterThan(47.9)
            ->and($veedor->hasRole(Roles::Observer->value))->toBeTrue();
    });

    Notification::assertSentTo($veedor, WelcomeNotification::class, fn (WelcomeNotification $n) => str_contains($n->url, 'veeduria-smr.govtrace.localhost'));
});

it('honors the 48-hour expiration exactly', function (int $hoursAgo, int $minutesAgo, bool $stillValid) {
    $veedor = $this->tenant->run(function () use ($hoursAgo, $minutesAgo) {
        $veedor = (new InviteObserver)->handle('carlos@correo.co');
        $veedor->forceFill(['invitation_expires_at' => now()->subHours($hoursAgo)->subMinutes($minutesAgo)->addHours(48)])->save();

        return $veedor;
    });

    $isValid = $this->tenant->run(fn () => $veedor->fresh()->invitation_expires_at->isFuture());

    expect($isValid)->toBe($stillValid);
})->with([
    'a un minuto de vencer, sigue válido' => [47, 59, true],
    'un minuto después de vencer, inválido' => [48, 1, false],
]);

it('rejects inviting an email already used or pending in the same organization', function (bool $alreadyActivated) {
    $this->tenant->run(function () use ($alreadyActivated) {
        $veedor = (new InviteObserver)->handle('carlos@correo.co');

        if ($alreadyActivated) {
            $veedor->forceFill(['password' => 'Veeduria#2026', 'invitation_token_hash' => null])->save();
        }
    });

    $this->tenant->run(fn () => (new InviteObserver)->handle('carlos@correo.co'));
})->with([
    'ya es un veedor activo' => [true],
    'tiene una invitación pendiente' => [false],
])->throws(OrganizationValidationException::class, 'Ya existe un usuario registrado o una invitación pendiente con este correo electrónico en la organización.');

it('lets the same email be a veedor in a different organization', function () {
    Notification::fake();

    $this->tenant->run(fn () => (new InviteObserver)->handle('carlos@correo.co'));

    $otherTenant = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');

    try {
        $veedorInOther = $otherTenant->run(fn () => (new InviteObserver)->handle('carlos@correo.co'));

        Notification::assertSentTo($veedorInOther, WelcomeNotification::class);

        $countInFirst = $this->tenant->run(fn () => User::query()->where('email', 'carlos@correo.co')->count());
        $countInOther = $otherTenant->run(fn () => User::query()->where('email', 'carlos@correo.co')->count());

        expect($countInFirst)->toBe(1)->and($countInOther)->toBe(1);
    } finally {
        $otherTenant->delete();
    }
});

it('never lets code running inside a tenant register a new organization', function () {
    $this->tenant->run(fn () => (new RegisterOrganization)->handle('901234567-7', 'Veeduría Nueva', 'veeduria-nueva'));
})->throws(OrganizationValidationException::class);
