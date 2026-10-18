<?php

namespace App\Domain\Calendar;

use Carbon\CarbonImmutable;

/**
 * It. 66a (R-LEG-12): los días hábiles de Colombia, sin sábados, domingos ni
 * festivos. Se cuentan desde el día siguiente, como los plazos en días.
 */
final class BusinessDays
{
    /** The $days-th business day after $day (a date, without its time). */
    public static function after(CarbonImmutable $day, int $days): CarbonImmutable
    {
        $current = $day->startOfDay();
        for ($counted = 0; $counted < $days;) {
            $current = $current->addDay();
            if (! $current->isWeekend() && ! ColombianHolidays::isHoliday($current)) {
                $counted++;
            }
        }

        return $current;
    }
}
