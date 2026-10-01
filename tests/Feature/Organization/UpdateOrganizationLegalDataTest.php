<?php

use App\Application\Organization\RegisterOrganization;
use App\Application\Organization\UpdateOrganizationLegalData;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Support\Facades\Auth;

/*
 * Iteración 6 — Datos legales, territorio, log de auditoría y parámetros
 * (specs/PLAN.md). Traduce features/US-011.feature. Sin RefreshDatabase
 * — registrar la organización de las Antecedentes ejecuta CREATE DATABASE.
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

it('Actualización exitosa del NIT con registro de auditoría: updates the NIT and writes an audit log entry', function () {
    Auth::guard('web')->login(SuperAdmin::factory()->create(['name' => 'Root Admin']));

    $updated = (new UpdateOrganizationLegalData)->handle($this->tenant, '901234567-7');

    expect($updated->nit)->toBe('901234567-7');

    $entry = AuditLog::query()->where('organization_id', $this->tenant->id)->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->action)->toBe('organization.legal_data_updated')
        ->and($entry->actor_type)->toBe('super_admin')
        ->and($entry->actor_name)->toBe('Root Admin')
        ->and($entry->before)->toBe(['nit' => '900123456-8'])
        ->and($entry->after)->toBe(['nit' => '901234567-7']);
});

it('No se puede cambiar a un NIT que ya usa otra organización: rejects a NIT already used by another organization', function () {
    $other = (new RegisterOrganization)->handle('890000062-6', 'Veeduría Ciénaga', 'veeduria-cienaga');

    try {
        (new UpdateOrganizationLegalData)->handle($this->tenant, '890000062-6');
    } finally {
        $other->delete();
    }
})->throws(OrganizationValidationException::class, 'Ya existe una organización registrada con el NIT ingresado.');

it('No se puede cambiar a un NIT con dígito de verificación inválido: rejects a NIT with an invalid check digit', function () {
    (new UpdateOrganizationLegalData)->handle($this->tenant, '901234567-2');
})->throws(OrganizationValidationException::class, 'El NIT ingresado no es válido o el dígito de verificación no coincide con el algoritmo de la DIAN.');

it('El Administrador de Organización no puede editar el NIT ni los datos legales: never lets code running inside a tenant edit legal data, even for its own organization', function () {
    $tenant = $this->tenant;

    expect(fn () => $tenant->run(fn () => (new UpdateOrganizationLegalData)->handle($tenant, '901234567-7')))
        ->toThrow(OrganizationValidationException::class);

    expect($tenant->fresh()->nit)->toBe('900123456-8');
});

// It. 43f (V8): la razón social, a solicitud formal, con las reglas del alta.

it('Actualización de la razón social con registro de auditoría: changes the legal name, and the log keeps who, the one before and the new one', function () {
    Auth::guard('web')->login(SuperAdmin::factory()->create(['name' => 'Root Admin']));

    $updated = (new UpdateOrganizationLegalData)->handle($this->tenant, '900123456-8', legalName: '  Veeduría Ciudadana del Distrito de Santa Marta  ');

    expect($updated->name)->toBe('Veeduría Ciudadana del Distrito de Santa Marta');
    $entry = AuditLog::query()->where('organization_id', $this->tenant->id)->latest('id')->first();
    expect($entry->action)->toBe('organization.legal_data_updated')
        ->and($entry->actor_name)->toBe('Root Admin')
        ->and($entry->before)->toBe(['nit' => '900123456-8', 'name' => 'Veeduría Ciudadana Santa Marta'])
        ->and($entry->after)->toBe(['nit' => '900123456-8', 'name' => 'Veeduría Ciudadana del Distrito de Santa Marta']);
});

it('La razón social cumple las reglas del alta: rejects a legal name of less than 3 characters, and keeps the one it had', function () {
    try {
        (new UpdateOrganizationLegalData)->handle($this->tenant, '900123456-8', legalName: 'VC');
    } finally {
        expect($this->tenant->refresh()->name)->toBe('Veeduría Ciudadana Santa Marta');
    }
})->throws(OrganizationValidationException::class, 'El nombre de la organización debe tener entre 3 y 150 caracteres.');

it('keeps the legal name when it does not change, and the log does not mention it', function () {
    (new UpdateOrganizationLegalData)->handle($this->tenant, '901234567-7', legalName: 'Veeduría Ciudadana Santa Marta');

    expect(AuditLog::query()->where('organization_id', $this->tenant->id)->latest('id')->first()->after)->toBe(['nit' => '901234567-7']);
});

it('keeps the name the organization shows on its site, if it has one: it is another field (US-007)', function () {
    $this->tenant->update(['display_name' => 'Ojo Ciudadano SMR']);

    $updated = (new UpdateOrganizationLegalData)->handle($this->tenant, '900123456-8', legalName: 'Veeduría Ciudadana del Distrito de Santa Marta');

    expect($updated->displayName())->toBe('Ojo Ciudadano SMR');
});
