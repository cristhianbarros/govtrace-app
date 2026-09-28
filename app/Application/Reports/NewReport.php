<?php

namespace App\Application\Reports;

use Carbon\CarbonInterface;

/**
 * What the PWA sends to create a report (US-008), as received — the
 * rules about each value live in the Domain (App\Domain\Reports).
 */
final class NewReport
{
    public function __construct(
        public readonly string $secopContractId,
        public readonly ?string $classification,
        public readonly ?string $comment,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?float $accuracyMeters,
        public readonly CarbonInterface $capturedAt,
    ) {}
}
