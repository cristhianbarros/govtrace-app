<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 24 — Agrupar varios contratos en una ficha de obra
 * (specs/PLAN.md). Traduce features/US-045-INT.feature (3 casos) contra
 * POST /worksites/group, del Administrador de Organización (R-INT-05).
 *
 * Un reporte nunca cambia de ficha: su obra es parte de lo que envió el
 * veedor (R-TA-02) y está sellada en la red. Por eso la ficha que ya tiene
 * reportes recibe a los demás contratos, y dos fichas con reportes no se
 * funden.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    app()->instance(SealingNetwork::class, new FakeSealingNetwork);

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
    reportableContract('CO1.PCCNTR.1111111', ['object' => 'Acueducto Gaira, fase 1']);
    reportableContract('CO1.PCCNTR.3333333', ['object' => 'Acueducto Gaira, fase 2']);
});

afterEach(function () {
    // Los que simulan al veedor que reporta al mismo tiempo.
    WorksiteContract::flushEventListeners();
    DB::purge('another_process');

    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
});

/**
 * Runs $statement on its own connection to the organization's database,
 * which commits at once: what another process — a veedor sending a report
 * — does at that very moment.
 */
function asAnotherProcess(Closure $statement): void
{
    config(['database.connections.another_process' => config('database.connections.tenant')]);
    $statement(DB::connection('another_process'));
    DB::purge('another_process');
}

/** @param  list<string>  $secopContractIds */
function groupContracts(OrganizationUser $member, string $name, array $secopContractIds): TestResponse
{
    return test()->actingAs($member, 'tenant')->postJson('http://veeduria-smr.govtrace.localhost/worksites/group', [
        'name' => $name,
        'secop_contract_ids' => $secopContractIds,
    ]);
}

/** A report of the veedor on the contract, from the Santa Marta worksite; returns the worksite it went to. */
function reportedWorksiteOf(string $secopContractId): int
{
    test()->flushSession();
    $reportId = sendReport(test()->veedor, ['secop_contract_id' => $secopContractId])->assertCreated()->json('id');
    tenancy()->end();

    return test()->tenant->run(fn () => Report::query()->findOrFail($reportId)->worksite_id);
}

it('Agrupación de dos contratos: the public view shows both, and a report on either goes to the whole worksite', function () {
    $worksiteId = groupContracts($this->administrator, 'Acueducto Gaira', ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'])
        ->assertCreated()
        ->json('data.id');

    $view = publicGet("/public/worksites/{$worksiteId}")->assertOk();

    expect($view->json('data.name'))->toBe('Acueducto Gaira')
        ->and(array_column($view->json('data.contracts'), 'secop_contract_id'))->toBe(['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'])
        ->and(reportedWorksiteOf('CO1.PCCNTR.1111111'))->toBe($worksiteId)
        ->and(reportedWorksiteOf('CO1.PCCNTR.3333333'))->toBe($worksiteId);
});

it('El pin toma el peor estado de los contratos agrupados: one in deadline and one overdue still in execution', function () {
    Contract::fromSecop(fn () => Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.3333333')->sole()->update(['end_date' => now()->subMonth()->toDateString()]));
    $gaira = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1111111'], santaMartaWorksiteLocation());

    groupContracts($this->administrator, 'Acueducto Gaira', ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'])->assertCreated();

    $pin = collect(publicGet('/public/worksites')->assertOk()->json('data'))->firstWhere('id', $gaira->id);
    expect($pin['color_pin'])->toBe('red');
});

it('Solo se agrupan contratos del territorio de la organización: one from Medellín is refused', function () {
    reportableContract('CO1.PCCNTR.5555555', ['department_code' => '05', 'municipality_code' => '05001', 'entity_name' => 'Alcaldía de Medellín']);

    groupContracts($this->administrator, 'Acueducto Gaira', ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.5555555'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['secop_contract_ids' => 'El contrato CO1.PCCNTR.5555555 no es del territorio de la organización.']);

    expect($this->tenant->run(fn () => WorksiteContract::query()->count()))->toBe(0);
});

// Reglas derivadas ------------------------------------------------------

it('lets only the Administrador group contracts', function () {
    groupContracts($this->veedor, 'Acueducto Gaira', ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'])->assertForbidden();
});

it('needs a name and at least two distinct contracts that exist', function (string $name, array $secopContractIds, string $field, string $message) {
    groupContracts($this->administrator, $name, $secopContractIds)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => $message]);

    expect($this->tenant->run(fn () => WorksiteContract::query()->count()))->toBe(0);
})->with([
    'sin nombre' => ['  ', ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'], 'name', 'La ficha necesita un nombre.'],
    'un solo contrato' => ['Acueducto Gaira', ['CO1.PCCNTR.1111111'], 'secop_contract_ids', 'Agrupar exige al menos dos contratos distintos.'],
    'el mismo dos veces' => ['Acueducto Gaira', ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.1111111'], 'secop_contract_ids', 'Agrupar exige al menos dos contratos distintos.'],
    'uno que no existe' => ['Acueducto Gaira', ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.9999999'], 'secop_contract_ids', 'No existe el contrato CO1.PCCNTR.9999999 en SECOP II.'],
]);

it('keeps the worksite that has reports, with its place: it takes the other contract, whose empty worksite goes away', function () {
    // La ficha vacía es la más antigua y también tiene ubicación: aun así, manda la que tiene reportes.
    $empty = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.3333333'], [11.2500, -74.2000]);
    $first = reportedWorksiteOf('CO1.PCCNTR.1111111');
    $firstLocation = $this->tenant->run(fn () => Worksite::query()->findOrFail($first)->location());

    $grouped = groupContracts($this->administrator, 'Acueducto Gaira', ['CO1.PCCNTR.3333333', 'CO1.PCCNTR.1111111'])
        ->assertCreated()
        ->json('data.id');

    $worksite = $this->tenant->run(fn () => Worksite::query()->with('contracts')->findOrFail($grouped));
    expect($grouped)->toBe($first)
        ->and($worksite->location())->toEqual($firstLocation)
        ->and($worksite->contracts->pluck('secop_contract_id')->sort()->values()->all())->toBe(['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'])
        ->and($this->tenant->run(fn () => Worksite::query()->whereKey($empty->id)->exists()))->toBeFalse()
        ->and($empty->id)->toBeLessThan($first)
        ->and(reportedWorksiteOf('CO1.PCCNTR.3333333'))->toBe($first);
});

it('refuses to merge two worksites that already have reports: a report never changes worksite', function () {
    reportedWorksiteOf('CO1.PCCNTR.1111111');
    reportedWorksiteOf('CO1.PCCNTR.3333333');

    groupContracts($this->administrator, 'Acueducto Gaira', ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['secop_contract_ids' => 'Los contratos CO1.PCCNTR.1111111 y CO1.PCCNTR.3333333 ya tienen reportes en fichas distintas, y un reporte no cambia de ficha.']);

    expect($this->tenant->run(fn () => Worksite::query()->count()))->toBe(2);
});

it('logs the grouping in the audit log, and the Administrador sees the name in the worksites of the panel', function () {
    $worksiteId = groupContracts($this->administrator, 'Acueducto Gaira', ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'])->json('data.id');

    $entry = AuditLog::query()->where('organization_id', $this->tenant->id)->where('action', 'worksite.contracts_grouped')->sole();
    expect($entry->actor_type)->toBe('organization_admin')
        ->and($entry->actor_id)->toBe((string) $this->administrator->id)
        ->and($entry->after)->toBe(['worksite_id' => $worksiteId, 'name' => 'Acueducto Gaira', 'secop_contract_ids' => ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333']]);

    $this->actingAs($this->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/worksites')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Acueducto Gaira');
});

it('fails cleanly, to be retried, when a veedor sends the first report of one of the contracts at that very moment', function () {
    $other = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.7777777'], santaMartaWorksiteLocation());
    // Justo antes de que la agrupación vincule 3333333, el reporte del veedor lo vinculó a su ficha.
    WorksiteContract::creating(function (WorksiteContract $link) use ($other) {
        if ($link->secop_contract_id === 'CO1.PCCNTR.3333333') {
            asAnotherProcess(fn ($db) => $db->table('worksite_contracts')->insert([
                'worksite_id' => $other->id, 'secop_contract_id' => 'CO1.PCCNTR.3333333', 'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    });

    groupContracts($this->administrator, 'Acueducto Gaira', ['CO1.PCCNTR.1111111', 'CO1.PCCNTR.3333333'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['secop_contract_ids' => 'Un veedor acaba de reportar sobre uno de estos contratos. Vuelva a intentar la agrupación.']);

    // De la agrupación no quedó nada: ni la ficha nueva ni el vínculo de 1111111.
    expect($this->tenant->run(fn () => WorksiteContract::query()->orderBy('secop_contract_id')->pluck('worksite_id', 'secop_contract_id')->all()))
        ->toBe(['CO1.PCCNTR.3333333' => $other->id, 'CO1.PCCNTR.7777777' => $other->id])
        ->and($this->tenant->run(fn () => Worksite::query()->count()))->toBe(1);
});

it('sends to the grouped worksite a report whose empty worksite was merged into it while it was being created', function () {
    $empty = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.3333333'], santaMartaWorksiteLocation());
    $gaira = worksiteWithContracts($this->tenant, ['CO1.PCCNTR.1111111'], santaMartaWorksiteLocation());
    // El reporte ya leyó que 3333333 es de la ficha vacía; en ese instante, la agrupación la funde en Gaira.
    $merged = false;
    WorksiteContract::retrieved(function (WorksiteContract $link) use ($empty, $gaira, &$merged) {
        if (! $merged && $link->secop_contract_id === 'CO1.PCCNTR.3333333') {
            $merged = true;
            asAnotherProcess(function ($db) use ($empty, $gaira) {
                $db->table('worksite_contracts')->where('secop_contract_id', 'CO1.PCCNTR.3333333')->update(['worksite_id' => $gaira->id]);
                $db->table('worksites')->where('id', $empty->id)->delete();
            });
        }
    });

    expect(reportedWorksiteOf('CO1.PCCNTR.3333333'))->toBe($gaira->id);
});
