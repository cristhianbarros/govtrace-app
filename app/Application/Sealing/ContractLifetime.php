<?php

namespace App\Application\Sealing;

use Carbon\CarbonImmutable;

/**
 * Hasta qué ledger viven la instancia y el código del contrato de sellado
 * (su TTL en Soroban). La tesorería los extiende (D12); si una vence, el
 * siguiente sello la restaura y esa restauración la paga la patrocinadora.
 */
final class ContractLifetime
{
    /** Stellar cierra un ledger cada ~5 s: el tiempo que queda es una estimación. */
    public const LEDGER_SECONDS = 5;

    public function __construct(
        public readonly int $latestLedger,
        public readonly int $instanceLiveUntil,
        public readonly int $codeLiveUntil,
    ) {}

    /** Whole days left until the entry that lives until $liveUntil expires. */
    public function daysLeft(int $liveUntil): int
    {
        return intdiv(max(0, $liveUntil - $this->latestLedger) * self::LEDGER_SECONDS, 86_400);
    }

    public function expiresAt(int $liveUntil, CarbonImmutable $now): CarbonImmutable
    {
        return $now->addSeconds(max(0, $liveUntil - $this->latestLedger) * self::LEDGER_SECONDS);
    }
}
