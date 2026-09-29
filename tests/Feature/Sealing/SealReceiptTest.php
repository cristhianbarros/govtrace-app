<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\ConfirmSeal;
use App\Jobs\SealReport;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 23 — Recibo de Inmutabilidad (specs/PLAN.md). Traduce
 * features/US-023.feature (3 casos, el del veedor) y US-025.feature (2, el
 * público), ya ajustadas a Stellar, contra los endpoints reales:
 * GET /reports/{id}/receipt (el veedor, solo sus reportes) y
 * GET /public/reports/{id}/receipt (cualquiera, si la evidencia está
 * publicada — o retirada: su sello sigue disponible para auditoría, US-037).
 * Las pantallas son de las it. 27 y 28.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const RECEIPT_PENDING = '⏳ Su evidencia está en proceso de sellado en la red Stellar. Este proceso toma unos minutos. El recibo criptográfico aparecerá aquí en breve.';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');
    Notification::fake();
    $this->network = new FakeSealingNetwork;
    app()->instance(SealingNetwork::class, $this->network);
    config(['stellar.explorer_url' => 'https://stellar.expert/explorer/testnet']);

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
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

function receiptFor(OrganizationUser $veedor, int $reportId): TestResponse
{
    return test()->actingAs($veedor, 'tenant')->getJson("http://veeduria-smr.govtrace.localhost/reports/{$reportId}/receipt");
}

function publicReceipt(int $reportId): TestResponse
{
    return test()->getJson("http://veeduria-smr.govtrace.localhost/public/reports/{$reportId}/receipt");
}

function sealRecord(int $reportId): ReportSeal
{
    return test()->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole());
}

/** A report whose first transaction never entered a ledger, resent and sealed by the second one. Returns [reportId, first tx, second tx]. */
function resentReport(): array
{
    test()->flushSession();
    $reportId = sendReport(test()->veedor)->assertCreated()->json('id');
    tenancy()->end();

    test()->network->closesLedgerRightAway = false;
    app()->call([new SealReport(test()->tenant->id, $reportId), 'handle']);
    $first = sealRecord($reportId)->tx_hash;

    // 5 minutos sin ledger: vuelve a la cola (US-021) y se reenvía.
    test()->travel(6)->minutes();
    app()->call([new ConfirmSeal(test()->tenant->id, $reportId), 'handle']);
    test()->network->closesLedgerRightAway = true;
    app()->call([new SealReport(test()->tenant->id, $reportId), 'handle']);
    app()->call([new ConfirmSeal(test()->tenant->id, $reportId), 'handle']);

    return [$reportId, $first, sealRecord($reportId)->tx_hash];
}

/** What the receipt must say for a seal. */
function expectedReceipt(ReportSeal $seal): array
{
    return [
        'sealed' => true,
        'merkle_root' => $seal->merkle_root,
        'tx_hash' => $seal->tx_hash,
        'ledger' => $seal->ledger,
        'sealed_at' => $seal->sealed_at->utc()->toIso8601String(),
        'contract_id' => FakeSealingNetwork::CONTRACT_ID,
        'explorer' => ['label' => 'Ver en Stellar Expert', 'url' => "https://stellar.expert/explorer/testnet/tx/{$seal->tx_hash}"],
    ];
}

// US-023: el recibo del veedor ------------------------------------------

it('Recibo de una evidencia sellada: root, transaction, ledger, exact ledger time and the button to Stellar Expert', function () {
    $reportId = sealedReport($this->tenant, $this->veedor);
    // Dentro de la organización: Eloquent necesita su conexión para interpretar la fecha.
    $expected = $this->tenant->run(fn () => expectedReceipt(ReportSeal::query()->where('report_id', $reportId)->sole()));

    receiptFor($this->veedor, $reportId)
        ->assertOk()
        ->assertExactJson(['data' => $expected]);
});

it('La evidencia aún no está sellada: the message, and nothing of the seal', function () {
    $this->flushSession();
    $reportId = sendReport($this->veedor)->assertCreated()->json('id');

    receiptFor($this->veedor, $reportId)
        ->assertOk()
        ->assertExactJson(['data' => ['sealed' => false, 'message' => RECEIPT_PENDING]]);
});

it('Evidencia reenviada tras una transacción que no se incluyó: only the transaction that entered a ledger', function () {
    [$reportId, $first, $second] = resentReport();

    $receipt = receiptFor($this->veedor, $reportId)->assertOk();

    expect($first)->not->toBe($second)
        ->and($receipt->json('data.tx_hash'))->toBe($second)
        ->and($receipt->json('data.ledger'))->toBe(sealRecord($reportId)->ledger)
        ->and($receipt->getContent())->not->toContain($first);
});

it('keeps the transaction that did not enter only in the audit log, next to the one that did', function () {
    [$reportId, $first, $second] = resentReport();

    $entry = AuditLog::query()->where('organization_id', $this->tenant->id)->where('action', 'seal.resent')->sole();

    expect($entry->organization_id)->toBe($this->tenant->id)
        ->and($entry->actor_type)->toBeNull() // "Sistema"
        ->and($entry->before)->toBe(['report_id' => $reportId, 'tx_hash' => $first])
        ->and($entry->after)->toBe(['report_id' => $reportId, 'tx_hash' => $second]);
});

it('logs nothing for a seal that entered on its first transaction', function () {
    sealedReport($this->tenant, $this->veedor);

    expect(AuditLog::query()->where('organization_id', $this->tenant->id)->where('action', 'seal.resent')->exists())->toBeFalse();
});

it('logs no resend when the recorded transaction was the one that entered, found once the RPC came back', function () {
    $this->flushSession();
    $reportId = sendReport($this->veedor)->assertCreated()->json('id');
    tenancy()->end();
    app()->call([new SealReport($this->tenant->id, $reportId), 'handle']);
    $sent = sealRecord($reportId)->tx_hash;

    // El RPC no responde al confirmar: a los 5 minutos, de vuelta a la cola (US-021), y el
    // reintento recibe "Hash ya registrado" — era la misma transacción, que sí había entrado.
    $this->travel(6)->minutes();
    $this->network->unavailableCalls = 1;
    app()->call([new ConfirmSeal($this->tenant->id, $reportId), 'handle']);
    app()->call([new SealReport($this->tenant->id, $reportId), 'handle']);

    expect(sealRecord($reportId)->only(['status', 'tx_hash']))->toBe(['status' => SealStatus::Sealed, 'tx_hash' => $sent])
        ->and(AuditLog::query()->where('organization_id', $this->tenant->id)->where('action', 'seal.resent')->exists())->toBeFalse();
});

it('shows the transaction that sealed the root when the answer to its sending never came', function () {
    $this->flushSession();
    $reportId = sendReport($this->veedor)->assertCreated()->json('id');
    tenancy()->end();

    // La primera no entra en 5 minutos: vuelve a la cola (US-021).
    $this->network->closesLedgerRightAway = false;
    app()->call([new SealReport($this->tenant->id, $reportId), 'handle']);
    $lost = sealRecord($reportId)->tx_hash;
    $this->travel(6)->minutes();
    app()->call([new ConfirmSeal($this->tenant->id, $reportId), 'handle']);

    // La segunda entra, pero la respuesta del RPC nunca llega: la anotada sigue siendo la primera.
    $this->network->closesLedgerRightAway = true;
    $this->network->timeoutsAfterSending = 1;
    app()->call([new SealReport($this->tenant->id, $reportId), 'handle']);
    // El reintento recibe "Hash ya registrado" y toma el sello que la red ya tiene.
    app()->call([new SealReport($this->tenant->id, $reportId), 'handle']);

    $sealedBy = collect($this->network->onChain)->sole()->txHash;
    expect($sealedBy)->not->toBe($lost)
        ->and(receiptFor($this->veedor, $reportId)->json('data.tx_hash'))->toBe($sealedBy)
        // La anotada que no entró, solo para auditoría.
        ->and(AuditLog::query()->where('organization_id', $this->tenant->id)->where('action', 'seal.resent')->sole()->only(['before', 'after']))->toBe([
            'before' => ['report_id' => $reportId, 'tx_hash' => $lost],
            'after' => ['report_id' => $reportId, 'tx_hash' => $sealedBy],
        ]);
});

it('shows the veedor the same message while the sealing is failing: he never sees an error', function () {
    $this->flushSession();
    $reportId = sendReport($this->veedor)->assertCreated()->json('id');
    $this->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->update(['status' => SealStatus::Failed, 'attempts' => 5]));

    receiptFor($this->veedor, $reportId)->assertExactJson(['data' => ['sealed' => false, 'message' => RECEIPT_PENDING]]);
});

it('only shows the private receipt to the veedor who sent the report', function () {
    $reportId = sealedReport($this->tenant, $this->veedor);
    $laura = reportingMember($this->tenant, 'laura@correo.co');

    receiptFor($laura, $reportId)->assertNotFound();
});

// US-025: el recibo público ---------------------------------------------

it('Recibo público de una evidencia publicada: anyone sees it, without a session', function () {
    $reportId = sealedReport($this->tenant, $this->veedor);
    editorialDecision($this->administrator, 'publish', $reportId)->assertOk();
    $expected = $this->tenant->run(fn () => expectedReceipt(ReportSeal::query()->where('report_id', $reportId)->sole()));
    auth('tenant')->logout();

    publicReceipt($reportId)
        ->assertOk()
        ->assertExactJson(['data' => $expected]);
});

it('Evidencia reenviada tras una transacción que no se incluyó (público): only the transaction that entered', function () {
    [$reportId, $first, $second] = resentReport();
    editorialDecision($this->administrator, 'publish', $reportId)->assertOk();

    $receipt = publicReceipt($reportId)->assertOk();

    expect($receipt->json('data.tx_hash'))->toBe($second)
        ->and($receipt->getContent())->not->toContain($first);
});

it('keeps the public receipt of a withdrawn evidence, for external audit, and has none for a hidden or rejected one', function () {
    $published = sealedReport($this->tenant, $this->veedor);
    $withdrawn = sealedReport($this->tenant, $this->veedor);
    $hidden = sealedReport($this->tenant, $this->veedor);
    $rejected = sealedReport($this->tenant, $this->veedor);
    editorialDecision($this->administrator, 'publish', $published)->assertOk();
    editorialDecision($this->administrator, 'publish', $withdrawn)->assertOk();
    editorialDecision($this->administrator, 'withdraw', $withdrawn, ['reason' => 'Solicitud del afectado'])->assertOk();
    editorialDecision($this->administrator, 'reject', $rejected, ['reason' => 'No corresponde'])->assertOk();

    publicReceipt($published)->assertOk();
    publicReceipt($withdrawn)->assertOk()->assertJsonPath('data.sealed', true);
    publicReceipt($hidden)->assertNotFound();
    publicReceipt($rejected)->assertNotFound();
});

it('points the explorer to the ledger when the network no longer knows which transaction sealed it', function () {
    $reportId = sealedReport($this->tenant, $this->veedor);
    // "Hash ya registrado" más de 7 días después: el RPC ya no tiene el evento, y nada quedó anotado.
    $this->tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->update(['tx_hash' => null]));
    $ledger = sealRecord($reportId)->ledger;

    receiptFor($this->veedor, $reportId)
        ->assertJsonPath('data.tx_hash', null)
        ->assertJsonPath('data.explorer', ['label' => 'Ver en Stellar Expert', 'url' => "https://stellar.expert/explorer/testnet/ledger/{$ledger}"]);
});

it('has no explorer button on a network without a public explorer, like the local one', function () {
    config(['stellar.explorer_url' => null]);
    $reportId = sealedReport($this->tenant, $this->veedor);

    receiptFor($this->veedor, $reportId)->assertJsonPath('data.explorer', null);
});
