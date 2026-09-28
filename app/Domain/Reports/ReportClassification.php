<?php

namespace App\Domain\Reports;

use App\Domain\Reports\Exceptions\ReportValidationException;

/** US-008: the only three kinds of report a veedor can send. */
enum ReportClassification: string
{
    case Progress = 'Avance';
    case Delay = 'Retraso';
    case Abandonment = 'Abandono';

    public static function fromInput(?string $value): self
    {
        if ($value === null || trim($value) === '') {
            throw ReportValidationException::classificationRequired();
        }

        return self::tryFrom($value) ?? throw ReportValidationException::invalidClassification();
    }
}
