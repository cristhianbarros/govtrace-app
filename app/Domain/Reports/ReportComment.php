<?php

namespace App\Domain\Reports;

use App\Domain\Reports\Exceptions\ReportValidationException;

/** US-008: optional, at most 500 characters. */
final class ReportComment
{
    private const MAX_LENGTH = 500;

    private function __construct(public readonly string $text) {}

    /** null when the veedor wrote nothing. */
    public static function fromInput(?string $text): ?self
    {
        $trimmed = trim((string) $text);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw ReportValidationException::commentTooLong(self::MAX_LENGTH);
        }

        return new self($trimmed);
    }
}
