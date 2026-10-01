<?php

use App\Application\Contracts\ProcessSecopContractRow;
use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Configuration\Parameters;
use App\Domain\Configuration\ParameterValue;
use App\Domain\Contracts\ArchivedContract;
use App\Domain\Contracts\Contract;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\ArchiveOldContracts;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
 * Iteración 25 — Archivado de contratos antiguos sin evidencias
 * (specs/PLAN.md). Traduce features/US-048-MNT.feature (4 casos), R-MNT-04:
 * una vez al mes, los contratos cerrados hace más de 5 años que ninguna
 * organización tiene en una ficha de obra pasan a archived_contracts; si
 * llega un reporte de uno de ellos, vuelve a la base principal y el reporte
 * sigue su curso.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    $this->lastParameterVersion = (int) ParameterValue::query()->max('id');

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('archived_contracts')->delete();
    DB::table('audit_logs')->delete();
    ParameterValue::query()->where('id', '>', $this->lastParameterVersion)->delete();
});

/** A contract of Santa Marta that SECOP shows closed since $endedAgo. */
function closedContract(string $secopContractId, DateInterval|string $endedAgo): Contract
{
    return reportableContract($secopContractId, [
        'status' => 'Cerrado',
        'end_date' => now()->sub(is_string($endedAgo) ? DateInterval::createFromDateString($endedAgo) : $endedAgo)->toDateString(),
        'object' => 'Parque de Bastidas',
        'value' => 480_000_000,
    ]);
}

/**
 * Reports on closed contracts that old, as if the Super Administrador had
 * widened the window (US-038-CFG): in force from right after the version
 * in force today, which the migration dated when it ran — so reports
 * captured from now on use it.
 */
function reportWindowOfMonths(int $months): void
{
    $latest = Carbon::parse(ParameterValue::query()->where('key', 'closed_contract_report_window_months')->max('effective_from'));

    Parameters::set('closed_contract_report_window_months', (string) $months, $latest->addSecond());
}

function runMonthlyArchive(): void
{
    app()->call([new ArchiveOldContracts, 'handle']);
}

function isArchived(string $secopContractId): bool
{
    return ArchivedContract::query()->where('secop_contract_id', $secopContractId)->exists()
        && ! Contract::query()->where('secop_contract_id', $secopContractId)->exists();
}

/** The SECOP row of the closed contract, as the nightly sync reads it. */
function closedContractRow(array $overrides = []): array
{
    return array_merge([
        'id_contrato' => 'CO1.PCCNTR.7654321',
        'referencia_del_contrato' => '118-2019',
        'nombre_entidad' => 'Alcaldía Distrital de Santa Marta',
        'proveedor_adjudicado' => 'Constructora Caribe S.A.S.',
        'descripcion_del_proceso' => 'Parque de Bastidas',
        'tipo_de_contrato' => 'Obra',
        'estado_contrato' => 'Cerrado',
        'valor_del_contrato' => '480000000.000000',
        'fecha_de_firma' => now()->subYears(6)->format('Y-m-d\T00:00:00.000'),
        'fecha_de_fin_del_contrato' => now()->subYears(5)->subMonth()->format('Y-m-d\T00:00:00.000'),
        'ciudad' => 'Santa Marta',
        'departamento' => 'Magdalena',
        'urlproceso' => ['url' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?x=2'],
    ], $overrides);
}

it('Qué contratos se archivan en la corrida mensual', function (string $endedAgo, int $evidences, bool $archived) {
    closedContract('CO1.PCCNTR.7654321', $endedAgo);
    if ($evidences > 0) {
        reportWindowOfMonths(120);
        foreach (range(1, $evidences) as $ignored) {
            $this->flushSession();
            sendReport($this->veedor, ['secop_contract_id' => 'CO1.PCCNTR.7654321', 'captured_at' => now()->toIso8601String()])->assertCreated();
            tenancy()->end();
        }
    }

    runMonthlyArchive();

    expect(isArchived('CO1.PCCNTR.7654321'))->toBe($archived)
        ->and(Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.7654321')->exists())->toBe(! $archived);
})->with([
    'sin evidencias, cerrado hace 5 años y 1 mes: se mueve al archivo' => ['5 years 1 month', 0, true],
    'sin evidencias, cerrado hace 4 años: sigue en la base principal' => ['4 years', 0, false],
    'con 2 evidencias, cerrado hace 8 años: sigue en la base principal' => ['8 years', 2, false],
]);

it('Un contrato archivado vuelve si llega una evidencia: the report that arrives is processed as usual', function () {
    closedContract('CO1.PCCNTR.7654321', '5 years 1 month');
    runMonthlyArchive();
    expect(isArchived('CO1.PCCNTR.7654321'))->toBeTrue();
    // La ventana vigente lo deja reportar: el reporte sigue las reglas de siempre.
    reportWindowOfMonths(72);

    $this->flushSession();
    sendReport($this->veedor, ['secop_contract_id' => 'CO1.PCCNTR.7654321', 'captured_at' => now()->toIso8601String()])->assertCreated();
    tenancy()->end();

    $contract = Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.7654321')->sole();
    expect(ArchivedContract::query()->count())->toBe(0)
        ->and($contract->only(['object', 'status']))->toBe(['object' => 'Parque de Bastidas', 'status' => 'Cerrado'])
        ->and($contract->value)->toBe('480000000.00');
});

// Reglas derivadas ------------------------------------------------------

it('keeps every column of the contract in the archive, and gives it back as it was', function () {
    $original = closedContract('CO1.PCCNTR.7654321', '6 years')->fresh();
    runMonthlyArchive();

    $archived = ArchivedContract::query()->where('secop_contract_id', 'CO1.PCCNTR.7654321')->sole();
    expect($archived->archived_at)->not->toBeNull()
        ->and(collect($archived->getAttributes())->except(['archived_at'])->all())->toEqual($original->getAttributes());
});

it('does not bring an archived contract back with the nightly sync, which keeps its archived copy current', function () {
    closedContract('CO1.PCCNTR.7654321', '5 years 1 month');
    runMonthlyArchive();

    (new ProcessSecopContractRow)->handle(closedContractRow(['proveedor_adjudicado' => 'Constructora Caribe SAS.']));

    expect(isArchived('CO1.PCCNTR.7654321'))->toBeTrue()
        ->and(ArchivedContract::query()->where('secop_contract_id', 'CO1.PCCNTR.7654321')->value('contractor_name'))->toBe('Constructora Caribe SAS.');
});

it('brings it back if SECOP reopens it', function () {
    closedContract('CO1.PCCNTR.7654321', '5 years 1 month');
    runMonthlyArchive();

    (new ProcessSecopContractRow)->handle(closedContractRow(['estado_contrato' => 'Modificado', 'fecha_de_fin_del_contrato' => now()->addYear()->format('Y-m-d\T00:00:00.000')]));

    expect(ArchivedContract::query()->count())->toBe(0)
        ->and(Contract::query()->where('secop_contract_id', 'CO1.PCCNTR.7654321')->value('status'))->toBe('Modificado');
});

it('does not archive a contract that an organization has in a worksite, even without reports', function () {
    closedContract('CO1.PCCNTR.7654321', '7 years');
    worksiteWithContracts($this->tenant, ['CO1.PCCNTR.7654321'], null);

    runMonthlyArchive();

    expect(isArchived('CO1.PCCNTR.7654321'))->toBeFalse();
});

it('archives only closed contracts: one SECOP still shows running or annulled stays', function () {
    reportableContract('CO1.PCCNTR.1111111', ['status' => 'En ejecución', 'end_date' => now()->subYears(7)->toDateString()]);
    reportableContract('CO1.PCCNTR.3333333', ['status' => 'cancelled', 'end_date' => now()->subYears(7)->toDateString()]);

    runMonthlyArchive();

    expect(ArchivedContract::query()->count())->toBe(0);
});

it('runs once a month, the first day, after the nightly sync', function () {
    Artisan::call('schedule:list', ['--timezone' => 'America/Bogota']); // it. 45a: la hora de Colombia

    expect(Artisan::output())->toMatch('/0\s+5\s+1\s+\*\s+\*\s+contracts-archive/');
});
