<?php

namespace App\Application\CitizenReports;

use App\Domain\Calendar\BusinessDays;
use App\Domain\CitizenReports\CitizenReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * It. 66a (US-069, R-LEG-12): until when the veeduría committed to answer a
 * citizen report — its own term, in business days, counted in Colombia — and
 * what is due soon or overdue. Not public: the citizen hears it when sending
 * and in the mail; the Administrador, in the daily digest.
 */
final class CitizenResponseDeadline
{
    public const ZONE = 'America/Bogota';

    /** Due soon: within these calendar days. */
    public const SOON_DAYS = 3;

    public static function dueFrom(CarbonImmutable $receivedAt, int $days): CarbonImmutable
    {
        return BusinessDays::after($receivedAt->setTimezone(self::ZONE), $days);
    }

    /** "La veeduría se compromete a responderle antes del martes 24 de noviembre de 2026 (15 días hábiles)." */
    public static function sentence(CarbonImmutable $dueOn, int $days): string
    {
        $date = $dueOn->locale('es')->translatedFormat('l j \d\e F \d\e Y');
        $term = $days === 1 ? '1 día hábil' : "{$days} días hábiles";

        return "La veeduría se compromete a responderle antes del {$date} ({$term}).";
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::ZONE)->startOfDay();
    }

    /** @return Builder<CitizenReport> without a first action, past their date */
    public static function overdue(): Builder
    {
        return self::waiting()->where('due_on', '<', self::today()->toDateString());
    }

    /** @return Builder<CitizenReport> without a first action, due from today to SOON_DAYS days ahead */
    public static function dueSoon(): Builder
    {
        return self::waiting()->whereBetween('due_on', [self::today()->toDateString(), self::today()->addDays(self::SOON_DAYS)->toDateString()]);
    }

    public static function isOverdue(CitizenReport $report): bool
    {
        return $report->status === 'new' && $report->first_action_at === null && $report->due_on !== null && $report->due_on->toDateString() < self::today()->toDateString();
    }

    /** @return Builder<CitizenReport> */
    private static function waiting(): Builder
    {
        return CitizenReport::query()->where('status', 'new')->whereNull('first_action_at')->whereNotNull('due_on');
    }
}
