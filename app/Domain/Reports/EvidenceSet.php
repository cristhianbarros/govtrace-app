<?php

namespace App\Domain\Reports;

use App\Domain\Reports\Exceptions\ReportValidationException;

/**
 * US-009: the files of one report — 1 to 5 photos, or exactly 1 PDF,
 * never mixed; 10 MB each at most; and every one byte-identical to what
 * the phone hashed (R-HASH-01), or nothing is queued for sealing.
 */
final class EvidenceSet
{
    public const MAX_PHOTOS = 5;

    public const MAX_BYTES_PER_FILE = 10 * 1024 * 1024;

    /** @param  non-empty-list<EvidenceUpload>  $uploads */
    private function __construct(public readonly array $uploads) {}

    /** @param  list<EvidenceUpload>  $uploads */
    public static function fromUploads(array $uploads): self
    {
        if ($uploads === []) {
            throw ReportValidationException::evidenceRequired();
        }

        foreach ($uploads as $upload) {
            if ($upload->kind() === null) {
                throw ReportValidationException::unsupportedEvidenceType();
            }

            if ($upload->sizeBytes > self::MAX_BYTES_PER_FILE) {
                throw ReportValidationException::evidenceTooLarge();
            }
        }

        $kinds = array_values(array_unique(array_map(fn (EvidenceUpload $upload) => $upload->kind()->value, $uploads)));

        if (count($kinds) > 1 || ($kinds[0] === EvidenceKind::Pdf->value && count($uploads) > 1)) {
            throw ReportValidationException::invalidEvidenceCombination();
        }

        if (count($uploads) > self::MAX_PHOTOS) {
            throw ReportValidationException::tooManyPhotos(self::MAX_PHOTOS);
        }

        foreach ($uploads as $upload) {
            if (! $upload->hashMatches()) {
                throw ReportValidationException::evidenceHashMismatch();
            }
        }

        return new self($uploads);
    }
}
