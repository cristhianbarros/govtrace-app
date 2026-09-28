<?php

namespace App\Application\Sealing;

use Carbon\CarbonImmutable;

/** Un sello tal como quedó en la red: el ledger que lo incluyó y la hora en que cerró. */
final class NetworkSeal
{
    public function __construct(
        public readonly int $ledger,
        public readonly CarbonImmutable $sealedAt,
        public readonly ?string $txHash = null,
    ) {}
}
