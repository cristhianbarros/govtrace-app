<?php

namespace Tests\Support;

use App\Application\Sealing\ContractLifetime;
use App\Application\Sealing\Exceptions\NetworkUnavailable;
use App\Application\Sealing\Exceptions\RootAlreadySealed;
use App\Application\Sealing\Exceptions\SealingNetworkError;
use App\Application\Sealing\Exceptions\SponsorOutOfFunds;
use App\Application\Sealing\NetworkSeal;
use App\Application\Sealing\SealingNetwork;
use Carbon\CarbonImmutable;

/**
 * La red de Stellar en memoria, para los escenarios de US-020b que no
 * necesitan una red real (make test). La implementación real, contra la
 * red local standalone, la prueba StellarSealingNetworkTest (make test-stellar).
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

    /** @var array<string, true> transacciones que la red procesó y rechazó */
    private array $failedTransactions = [];

    /** @var array<string, array{root: string, closed: bool}> */
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

        if (isset($this->onChain[$merkleRoot])) {
            throw new RootAlreadySealed($merkleRoot);
        }

        $this->submissions[] = ['worksite' => $worksiteReference, 'root' => $merkleRoot];
        $txHash = hash('sha256', $merkleRoot.count($this->submissions));
        $this->transactions[$txHash] = ['root' => $merkleRoot, 'closed' => false];

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
