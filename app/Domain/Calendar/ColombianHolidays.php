<?php

namespace App\Domain\Calendar;

use Carbon\CarbonImmutable;

/**
 * It. 66a (R-LEG-12): los festivos de Colombia de un año (Ley 51 de 1983).
 * Seis caen siempre en su fecha; siete se trasladan al lunes siguiente si no
 * caen en lunes ("Ley Emiliani"); y cinco dependen de la Pascua: el Jueves y
 * el Viernes Santo, en su fecha, y la Ascensión, el Corpus Christi y el
 * Sagrado Corazón, trasladados al lunes.
 */
final class ColombianHolidays
{
    private const FIXED = ['01-01', '05-01', '07-20', '08-07', '12-08', '12-25'];

    private const MOVED_TO_MONDAY = ['01-06', '03-19', '06-29', '08-15', '10-12', '11-01', '11-11'];

    /** @var array<int, list<string>> */
    private static array $years = [];

    /** @return list<string> the holidays of $year, as Y-m-d, in order */
    public static function of(int $year): array
    {
        return self::$years[$year] ??= self::compute($year);
    }

    public static function isHoliday(CarbonImmutable $day): bool
    {
        return in_array($day->toDateString(), self::of($day->year), true);
    }

    /** @return list<string> */
    private static function compute(int $year): array
    {
        $easter = self::easter($year);
        $days = [
            ...array_map(fn (string $date) => CarbonImmutable::parse("{$year}-{$date}"), self::FIXED),
            ...array_map(fn (string $date) => self::nextMonday(CarbonImmutable::parse("{$year}-{$date}")), self::MOVED_TO_MONDAY),
            $easter->subDays(3), // Jueves Santo
            $easter->subDays(2), // Viernes Santo
            self::nextMonday($easter->addDays(39)), // Ascensión del Señor
            self::nextMonday($easter->addDays(60)), // Corpus Christi
            self::nextMonday($easter->addDays(68)), // Sagrado Corazón
        ];
        $dates = array_map(fn (CarbonImmutable $day) => $day->toDateString(), $days);
        sort($dates);

        return array_values(array_unique($dates));
    }

    private static function nextMonday(CarbonImmutable $day): CarbonImmutable
    {
        return $day->isMonday() ? $day : $day->next(CarbonImmutable::MONDAY);
    }

    /** Easter Sunday of the Gregorian calendar (the anonymous algorithm of Meeus/Jones/Butcher). */
    private static function easter(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }
}
