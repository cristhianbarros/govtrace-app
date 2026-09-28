<?php

namespace App\Domain\Reports;

/**
 * US-009: what a report can carry — photos, already optimized to JPEG on
 * the phone (R-PRIV-06), or a PDF. No video.
 */
enum EvidenceKind: string
{
    case Photo = 'photo';
    case Pdf = 'pdf';

    /** By the MIME type detected from the file's content, not its name. */
    public static function fromMimeType(string $mimeType): ?self
    {
        return match ($mimeType) {
            'image/jpeg' => self::Photo,
            'application/pdf' => self::Pdf,
            default => null,
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Photo => 'jpg',
            self::Pdf => 'pdf',
        };
    }
}
