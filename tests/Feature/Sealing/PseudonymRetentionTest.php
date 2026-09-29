<?php

use App\Application\Organization\RegisterOrganization;
use App\Application\Publication\OpenData;
use App\Application\Reports\EvidenceExport;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Report;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Domain\Sealing\VeedorPseudonym;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\PurgeVeedorPseudonyms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 35 — R-MNT-03: la tabla seudónimo→veedor (D7) se conserva 5
 * años. Cada día se borra la fila de un veedor que lleva 5 años sin
 * reportar: desde entonces nadie puede volver de su seudónimo a su
 * persona por esa tabla. Sus reportes y sus sellos no cambian — el JSON
 * sellado ya llevaba el seudónimo, nunca su ID —, y las exportaciones no
 * la vuelven a crear.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

beforeEach(function () {
    $this->artisan('migrate');
    Notification::fake();
    Carbon::setTestNow('2026-09-29 12:00:00');
    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('contracts')->delete();
    DB::table('audit_logs')->delete();
    Carbon::setTestNow();
});

/** A veedor whose pseudonym was first sealed at $since and who last reported at $lastReport — that one, published and sealed. */
function veedorWithPseudonym(string $email, string $since, string $lastReport): OrganizationUser
{
    return test()->tenant->run(function () use ($email, $since, $lastReport) {
        $veedor = OrganizationUser::create(['name' => 'Veedor', 'email' => $email, 'password' => 'Veeduria#2026']);
        $worksite = Worksite::create(['latitude' => 11.2408, 'longitude' => -74.1990, 'located_at' => $since]);

        Carbon::setTestNow($since);
        VeedorPseudonym::of($veedor);
        foreach (array_unique([$since, $lastReport]) as $moment) {
            $report = Report::create([
                'user_id' => $veedor->id, 'worksite_id' => $worksite->id, 'classification' => 'Avance',
                'latitude' => 11.2408, 'longitude' => -74.1990, 'accuracy_meters' => 15, 'geofence_radius_meters' => 500,
                'captured_at' => $moment, 'received_at' => $moment,
                'editorial_status' => $moment === $lastReport ? EditorialStatus::Published : EditorialStatus::Hidden,
            ]);
        }
        ReportSeal::create([
            'report_id' => $report->id, 'status' => SealStatus::Sealed, 'merkle_root' => hash('sha256', $email),
            'ledger' => 1000, 'sealed_at' => $lastReport, 'tx_hash' => hash('sha256', "tx-{$email}"), 'contract_id' => FakeSealingNetwork::CONTRACT_ID,
        ]);
        Carbon::setTestNow('2026-09-29 12:00:00');

        return $veedor;
    });
}

function purgePseudonyms(): void
{
    app()->call([new PurgeVeedorPseudonyms, 'handle']);
}

/** @return list<int> the veedores whose pseudonym still maps back to them */
function pseudonymOwners(): array
{
    return test()->tenant->run(fn () => VeedorPseudonym::query()->orderBy('user_id')->pluck('user_id')->all());
}

it('deletes the pseudonym of a veedor who has not reported in 5 years, and keeps the one who still reports', function () {
    $gone = veedorWithPseudonym('antiguo@correo.co', '2020-03-01 12:00:00', '2021-09-01 12:00:00');
    $active = veedorWithPseudonym('vigente@correo.co', '2020-03-01 12:00:00', '2026-01-10 12:00:00');

    purgePseudonyms();

    expect(pseudonymOwners())->toBe([$active->id]);
    // Sus reportes siguen ahí, con su autoría.
    expect($this->tenant->run(fn () => Report::query()->where('user_id', $gone->id)->count()))->toBe(2);
});

it('counts the 5 years from the last report, to the minute', function (string $lastReport, bool $kept) {
    $veedor = veedorWithPseudonym('carlos@correo.co', '2020-03-01 12:00:00', $lastReport);

    purgePseudonyms();

    expect(pseudonymOwners())->toBe($kept ? [$veedor->id] : []);
})->with([
    'hace 5 años menos un minuto: se conserva' => ['2021-09-29 12:01:00', true],
    'hace 5 años justos: se borra' => ['2021-09-29 12:00:00', false],
]);

it('does it in every organization, also a suspended or decommissioned one, and records it in the audit log', function () {
    $this->tenant->update(['status' => 'decommissioned', 'decommissioned_at' => '2022-01-01 00:00:00']);
    veedorWithPseudonym('antiguo@correo.co', '2020-03-01 12:00:00', '2021-09-01 12:00:00');
    veedorWithPseudonym('otro@correo.co', '2020-03-01 12:00:00', '2021-06-01 12:00:00');

    purgePseudonyms();
    purgePseudonyms(); // al día siguiente no queda nada que borrar

    expect(pseudonymOwners())->toBe([]);
    $entry = AuditLog::query()->where('action', 'organization.pseudonyms_purged')->sole();
    expect($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_type)->toBe('system')
        ->and($entry->after)->toBe(['pseudonyms_deleted' => 2]);
});

it('does not bring the link back when the organization exports or publishes its data (US-050-RPT, US-052-RPT)', function () {
    $gone = veedorWithPseudonym('antiguo@correo.co', '2020-03-01 12:00:00', '2021-09-01 12:00:00');
    $sealed = $this->tenant->run(fn () => VeedorPseudonym::query()->where('user_id', $gone->id)->value('pseudonym'));
    purgePseudonyms();

    $this->tenant->run(fn () => iterator_to_array((new EvidenceExport)->rows(), false));
    $published = $this->tenant->run(fn () => (new OpenData)->records());

    // Los datos abiertos siguen diciendo el mismo seudónimo: se calcula, no se consulta.
    expect(pseudonymOwners())->toBe([])
        ->and($published[0]['seudonimo_veedor'])->toBe($sealed);
});

it('purges every day', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toMatch('/30\s+6\s+\*\s+\*\s+\*\s+veedor-pseudonyms-purge/');
});
