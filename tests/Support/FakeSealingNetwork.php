<?php

namespace Tests\Support;

use App\Application\Sealing\Exceptions\RootAlreadySealed;
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
    /** @var list<array{worksite: string, root: string}> */
    public array $submissions = [];

    /** @var array<string, NetworkSeal> raíz => sello ya registrado en la red */
    public array $onChain = [];

    public bool $sponsorFunded = true;

    /** false = la red recibe la transacción pero todavía no cierra el ledger que la incluye. */
    public bool $closesLedgerRightAway = true;

    /** @var array<string, array{root: string, closed: bool}> */
    private array $transactions = [];

    private int $ledger = 1200;

    public function submitSeal(string $worksiteReference, string $merkleRoot): string
    {
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

        return $txHash;
    }

    /** La red cierra el ledger que incluye la transacción. */
    public function closeLedgerWith(string $txHash): void
    {
        $this->transactions[$txHash]['closed'] = true;
        $this->onChain[$this->transactions[$txHash]['root']] = new NetworkSeal(++$this->ledger, CarbonImmutable::now()->startOfSecond(), $txHash);
    }

    public function transactionStatus(string $txHash): ?NetworkSeal
    {
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

    public function sponsorAddress(): string
    {
        return 'GFAKESPONSORGOVTRACEDEPRUEBASXXXXXXXXXXXXXXXXXXXXXXXXXX';
    }
}
