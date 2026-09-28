<?php

namespace App\Application\Reports;

use App\Domain\Reports\EvidenceUpload;
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
        /** @var list<EvidenceUpload> the photos or the PDF, each with the hash the phone computed */
        public readonly array $files,
    ) {}
}
