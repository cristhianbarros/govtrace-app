<?php

use App\Application\Organization\AssignInitialAdministrator;
use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\InviteObserver;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLabels;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 44c — Los impedimentos del veedor (features/US-057-LEG.feature,
 * docs/proceso-actual.md A4). El artículo 19 de la Ley 850 de 2003 impide
 * ser veedor a quien tiene un conflicto de interés con la obra. GovTrace no
 * puede comprobarlo: registra la declaración del veedor, con su fecha, al
 * activar su cuenta — o antes de su próximo reporte, si ya la tenía —, y no
 * recibe reportes de quien no la ha hecho (R-LEG-05).
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const DECLARATION_HOST = 'http://veeduria-smr.govtrace.localhost';
const DECLARE_FIRST = 'Antes de reportar, declare que no tiene impedimentos para ser veedor (Ley 850 de 2003, art. 19).';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    reportableContract('CO1.PCCNTR.1234567');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

/** @return array{0: User, 1: string} an invited member and the token of their link */
function invited(Closure $invite): array
{
    return test()->tenant->run(function () use ($invite) {
        $member = $invite();
        $plain = Str::random(64);
        $member->forceFill(['invitation_token_hash' => hash('sha256', $plain)])->save();

        return [$member, $plain];
    });
}

/** @param  array<string, mixed>  $extra */
function activate(User $member, string $token, array $extra = []): TestResponse
{
    // US-058-LEG (it. 44e): quien activa su cuenta autoriza además el tratamiento de sus datos.
    return test()->post(DECLARATION_HOST."/set-password/{$member->public_id}", ['token' => $token, 'password' => 'Veeduria#2026', 'password_confirmation' => 'Veeduria#2026', 'data_authorization' => true, ...$extra]);
}

/** A veedor whose account predates the declaration: active, and never asked. */
function veedorWithoutDeclaration(): User
{
    return test()->tenant->run(function () {
        $veedor = User::create(['name' => 'Carlos Pérez', 'email' => 'carlos@correo.co', 'password' => 'Veeduria#2026']);
        $veedor->assignRole(Roles::Observer->value);

        return $veedor;
    });
}

function declaredAt(User $member): ?string
{
    return test()->tenant->run(fn () => User::query()->findOrFail($member->id)->impediments_declared_at?->toIso8601String());
}

it('El veedor declara sus impedimentos al activar su cuenta: the screen asks for it, and the account is activated with its date', function () {
    [$veedor, $token] = invited(fn () => (new InviteObserver)->handle('carlos@correo.co'));

    $this->get(DECLARATION_HOST."/set-password/{$veedor->public_id}?token={$token}")
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/SetPassword')->where('valid', true)->where('declaration', true));
    tenancy()->end();

    $this->travelTo('2026-09-30 15:00:00');
    activate($veedor, $token, ['declaration' => true])->assertRedirect(DECLARATION_HOST.'/reports/new');
    tenancy()->end();

    expect(declaredAt($veedor))->toBe('2026-09-30T15:00:00+00:00')
        ->and($this->tenant->run(fn () => User::query()->findOrFail($veedor->id)->is_active))->toBeTrue();
});

it('Sin la declaración no se activa la cuenta de un veedor: it says why, and the invitation stays pending', function () {
    [$veedor, $token] = invited(fn () => (new InviteObserver)->handle('carlos@correo.co'));

    activate($veedor, $token)->assertSessionHasErrors(['declaration' => 'Para ser veedor, declare que no está en ninguno de estos casos.']);
    $this->assertGuest('tenant');
    tenancy()->end();

    expect(declaredAt($veedor))->toBeNull()
        ->and($this->tenant->run(fn () => User::query()->findOrFail($veedor->id)->invitation_token_hash))->not->toBeNull();
});

it('El Administrador activa su cuenta sin declarar impedimentos de veedor: it is not asked', function () {
    $this->tenant->run(fn () => User::query()->whereKey($this->administrator->id)->delete());
    [$administrator, $token] = invited(fn () => (new AssignInitialAdministrator)->handle($this->tenant, 'Marta Ospina', 'marta@veeduria.org'));
    tenancy()->end();

    $this->get(DECLARATION_HOST."/set-password/{$administrator->public_id}?token={$token}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('valid', true)->where('declaration', false));
    tenancy()->end();

    activate($administrator, $token)->assertRedirect();
    tenancy()->end();

    expect($this->tenant->run(fn () => User::query()->findOrFail($administrator->id)->is_active))->toBeTrue()
        ->and(declaredAt($administrator))->toBeNull();
});

it('Un veedor que ya tenía cuenta declara antes de su próximo reporte: "Nuevo Reporte" takes them to the declaration, and back', function () {
    $veedor = veedorWithoutDeclaration();

    $this->actingAs($veedor, 'tenant')->get(DECLARATION_HOST.'/reports/new')->assertRedirect(DECLARATION_HOST.'/declaration');
    $this->actingAs($veedor, 'tenant')->get(DECLARATION_HOST.'/declaration')
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Veedor/Declaration'));

    // Sin marcar la casilla, no vale.
    $this->actingAs($veedor, 'tenant')->post(DECLARATION_HOST.'/declaration', [])
        ->assertSessionHasErrors(['declaration' => 'Para ser veedor, declare que no está en ninguno de estos casos.']);

    $this->actingAs($veedor, 'tenant')->post(DECLARATION_HOST.'/declaration', ['declaration' => true])->assertRedirect(DECLARATION_HOST.'/reports/new');
    $this->actingAs($veedor, 'tenant')->get(DECLARATION_HOST.'/reports/new')->assertOk();
    tenancy()->end();

    expect(declaredAt($veedor))->not->toBeNull();
});

it('Sin declarar no se recibe un reporte: 403, not 422, so a phone without signal keeps it and sends it later', function () {
    $veedor = veedorWithoutDeclaration();

    // La sincronización sin conexión solo descarta un reporte con un 422 (resources/js/lib/sync.js).
    sendReport($veedor)->assertForbidden()->assertJson(['message' => DECLARE_FIRST]);
    tenancy()->end();
    expect($this->tenant->run(fn () => Report::query()->count()))->toBe(0);

    $this->actingAs($veedor, 'tenant')->post(DECLARATION_HOST.'/declaration', ['declaration' => true])->assertRedirect();
    sendReport($veedor)->assertCreated();
});

it('La declaración queda en el log de auditoría: who declared, and when', function () {
    $veedor = veedorWithoutDeclaration();

    $this->actingAs($veedor, 'tenant')->post(DECLARATION_HOST.'/declaration', ['declaration' => true])->assertRedirect();
    [$invitedVeedor, $token] = invited(fn () => (new InviteObserver)->handle('luisa@correo.co'));
    activate($invitedVeedor, $token, ['declaration' => true])->assertRedirect();
    tenancy()->end();

    $entries = AuditLog::query()->where('action', 'observer.impediments_declared')->orderBy('id')->get();
    expect($entries)->toHaveCount(2)
        ->and($entries[0]->organization_id)->toBe($this->tenant->id)
        ->and($entries[0]->actor_type)->toBe('observer')
        ->and($entries[0]->actor_id)->toBe((string) $veedor->id)
        ->and($entries[0]->after)->toMatchArray(['user_id' => $veedor->id, 'email' => 'carlos@correo.co'])
        ->and($entries[1]->after)->toMatchArray(['email' => 'luisa@correo.co'])
        ->and(AuditLabels::action('observer.impediments_declared'))->toBe('Declaró no tener impedimentos para ser veedor')
        ->and(AuditLabels::actor('observer', 'Carlos Pérez'))->toBe('Carlos Pérez · Veedor de Campo');
});

it('El Administrador ve qué veedores declararon: who declared and when, and who has not yet', function () {
    $pending = veedorWithoutDeclaration();
    $declared = reportingMember($this->tenant, 'luisa@correo.co');

    $rows = collect($this->actingAs($this->administrator, 'tenant')->getJson(DECLARATION_HOST.'/observers')->assertOk()->json('data'))->keyBy('email');

    expect($rows['carlos@correo.co']['impediments_declared_at'])->toBeNull()
        ->and($rows['luisa@correo.co']['impediments_declared_at'])->toBe(declaredAt($declared))
        ->and(declaredAt($pending))->toBeNull();
});

it('does not ask an Administrador for the declaration, nor let a visitor declare', function () {
    $this->actingAs($this->administrator, 'tenant')->post(DECLARATION_HOST.'/declaration', ['declaration' => true])->assertForbidden();
    publicGet('/declaration')->assertUnauthorized();
});
