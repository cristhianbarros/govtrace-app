<?php

namespace App\Application\Sealing;

use Carbon\CarbonImmutable;

/**
 * Un sello tal como quedó en la red: el ledger que lo incluyó, la hora en
 * que cerró y, si la red todavía recuerda la transacción, la comisión que
 * le cobró a la patrocinadora (US-004).
 */
final class NetworkSeal
{
    public function __construct(
        public readonly int $ledger,
        public readonly CarbonImmutable $sealedAt,
        public readonly ?string $txHash = null,
        public readonly ?int $feeStroops = null,
    ) {}
}
