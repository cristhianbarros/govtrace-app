<?php

namespace Tests\Support;

use App\Application\Sealing\ContractLifetime;
use App\Application\Sealing\Exceptions\NetworkUnavailable;
use App\Application\Sealing\Exceptions\RootAlreadySealed;
use App\Application\Sealing\Exceptions\SealingNetworkBusy;
use App\Application\Sealing\Exceptions\SealingNetworkError;
use App\Application\Sealing\Exceptions\SponsorOutOfFunds;
use App\Application\Sealing\NetworkSeal;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Sealing\SealingRetryPolicy;
use Carbon\CarbonImmutable;

/**
 * La red de Stellar en memoria, para los escenarios de US-020b que no
 * necesitan una red real (make test). La implementación real, contra la
 * red local standalone, la prueba StellarSealingNetworkTest (make test-stellar).
 *
 * Como Stellar (it. 39), admite una sola transacción pendiente de la
 * selladora: hasta que la anterior entre en un ledger, falle o venza a los
 * 4 minutos, otro sello recibe "ocupada" y vuelve en unos segundos.
 */
final class FakeSealingNetwork implements SealingNetwork
{
    /** A valid contract address (StrKey of sha256("govtrace-fake-sealing-contract")), not deployed anywhere. */
    public const CONTRACT_ID = 'CALFTQY2X3YTEHFZORY7FQJUPXB2BXEGBCCHQVWTKB3WAVA65QHMTSFA';

    /** A valid account address (StrKey of sha256("govtrace-fake-sponsor")), with no secret anywhere. */
    public const SPONSOR_ADDRESS = 'GCMN776RZKGVGGLLL2KB5YW322QOAA2TAHINWC6322RE2VBX4UWRSNGF';

    /** What a steady seal costs on testnet (it. 14): 0,2425 XLM. */
    public const FEE_STROOPS = 2_425_000;

    /** @var list<array{worksite: string, root: string}> */
    public array $submissions = [];

    /** @var array<string, NetworkSeal> raíz => sello ya registrado en la red */
    public array $onChain = [];

    public bool $sponsorFunded = true;

    /** 1.000 XLM: well above the alert threshold (US-022). */
    public int $sponsorBalanceStroops = 10_000_000_000;

    public int $balanceReads = 0;

    /** Until which ledger the instance and the code of the contract live (D12). */
    public ContractLifetime $lifetime;

    /** false = la red recibe la transacción pero todavía no cierra el ledger que la incluye. */
    public bool $closesLedgerRightAway = true;

    /** Cuántas llamadas seguidas fallan como si el RPC de Stellar no respondiera (US-021). */
    public int $unavailableCalls = 0;

    /** Cuántos envíos la red recibe, pero su respuesta no llega (timeout): la transacción sí sigue su curso. */
    public int $timeoutsAfterSending = 0;

    /** Cuántas veces un sello encontró la selladora con otra transacción pendiente (it. 39). */
    public int $busyRefusals = 0;

    /** What a busy network tells the seal: come back in this many seconds. */
    public const BUSY_RETRY_SECONDS = 3;

    /** @var array<string, true> transacciones que la red procesó y rechazó */
    private array $failedTransactions = [];

    /** @var array<string, array{root: string, closed: bool, valid_until: CarbonImmutable}> */
    private array $transactions = [];

    private int $ledger = 1200;

    public function __construct()
    {
        // Recién extendidas por la tesorería: ~180 días.
        $this->lifetime = new ContractLifetime($this->ledger, $this->ledger + 180 * 17_280, $this->ledger + 180 * 17_280);
    }

    public function submitSeal(string $worksiteReference, string $merkleRoot): string
    {
        $this->failIfUnavailable();

        if (! $this->sponsorFunded) {
            throw new SponsorOutOfFunds('La cuenta patrocinadora no tiene XLM para la comisión.');
        }

        // Una sola pendiente de la selladora, como en Stellar: la otra espera su turno.
        if ($pending = $this->pendingTransaction()) {
            $this->busyRefusals++;

            throw new SealingNetworkBusy(self::BUSY_RETRY_SECONDS, "La cuenta selladora tiene pendiente la transacción {$pending}.");
        }

        if (isset($this->onChain[$merkleRoot])) {
            throw new RootAlreadySealed($merkleRoot);
        }

        $this->submissions[] = ['worksite' => $worksiteReference, 'root' => $merkleRoot];
        $txHash = hash('sha256', $merkleRoot.count($this->submissions));
        $this->transactions[$txHash] = [
            'root' => $merkleRoot,
            'closed' => false,
            'valid_until' => CarbonImmutable::now()->addSeconds(SealingRetryPolicy::TRANSACTION_VALIDITY_SECONDS),
        ];

        if ($this->closesLedgerRightAway) {
            $this->closeLedgerWith($txHash);
        }

        if ($this->timeoutsAfterSending > 0) {
            $this->timeoutsAfterSending--;

            throw new NetworkUnavailable('La red de Stellar no respondió (sendTransaction): cURL error 28: Operation timed out');
        }

        return $txHash;
    }

    /** La red cierra el ledger que incluye la transacción. */
    public function closeLedgerWith(string $txHash): void
    {
        $this->transactions[$txHash]['closed'] = true;
        $this->onChain[$this->transactions[$txHash]['root']] = new NetworkSeal(++$this->ledger, CarbonImmutable::now()->startOfSecond(), $txHash, self::FEE_STROOPS);
    }

    /** La red procesa la transacción y la rechaza. */
    public function failTransaction(string $txHash): void
    {
        $this->failedTransactions[$txHash] = true;
    }

    /** The transaction of the sealer still waiting for a ledger: not closed, not rejected, and within its time bounds. */
    private function pendingTransaction(): ?string
    {
        foreach ($this->transactions as $txHash => $transaction) {
            if (! $transaction['closed'] && ! isset($this->failedTransactions[$txHash]) && $transaction['valid_until']->isFuture()) {
                return $txHash;
            }
        }

        return null;
    }

    public function transactionStatus(string $txHash): ?NetworkSeal
    {
        $this->failIfUnavailable();

        if (isset($this->failedTransactions[$txHash])) {
            throw new SealingNetworkError("La transacción {$txHash} falló en la red (FAILED).");
        }

        $transaction = $this->transactions[$txHash] ?? null;

        return $transaction && $transaction['closed'] ? $this->onChain[$transaction['root']] : null;
    }

    public function findSeal(string $merkleRoot): ?NetworkSeal
    {
        return $this->onChain[$merkleRoot] ?? null;
    }

    public function sponsorCanPay(): bool
    {
        return $this->sponsorFunded;
    }

    public function contractId(): string
    {
        return self::CONTRACT_ID;
    }

    public function sponsorAddress(): string
    {
        return self::SPONSOR_ADDRESS;
    }

    public function sponsorBalance(): int
    {
        $this->failIfUnavailable();
        $this->balanceReads++;

        return $this->sponsorBalanceStroops;
    }

    public function contractLifetime(): ContractLifetime
    {
        $this->failIfUnavailable();

        return $this->lifetime;
    }

    private function failIfUnavailable(): void
    {
        if ($this->unavailableCalls > 0) {
            $this->unavailableCalls--;

            throw new NetworkUnavailable('La red de Stellar no respondió: RPC sin conexión.');
        }
    }
}
