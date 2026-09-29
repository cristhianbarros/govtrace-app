<?php

namespace App\Domain\Worksites;

use App\Domain\Reports\ReportClassification;

/**
 * US-027: the color of a worksite's pin on the public map — green is
 * normal, yellow an alert, red at risk. The worst one wins: between what
 * the evidence says and what the contracts say, and among the contracts
 * a worksite groups (R-INT-05).
 */
enum PinColor: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    /** What the latest published evidence says: "Retraso" is an alert, "Abandono" a risk. */
    public static function ofEvidence(?ReportClassification $classification): self
    {
        return match ($classification) {
            ReportClassification::Delay => self::Yellow,
            ReportClassification::Abandonment => self::Red,
            ReportClassification::Progress, null => self::Green,
        };
    }

    public function worst(self $other): self
    {
        return $this->severity() >= $other->severity() ? $this : $other;
    }

    private function severity(): int
    {
        return match ($this) {
            self::Green => 0,
            self::Yellow => 1,
            self::Red => 2,
        };
    }
}
