<?php

namespace App\Application\Publication;

use App\Domain\Worksites\VisitorKey;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteVisitDay;
use App\Domain\Worksites\WorksiteVisitor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * It. 69 (US-071, R-PRIV-07): how many people see the page of each work,
 * without keeping who. The IP and the browser never reach a table: with them,
 * the organization and the work, a fingerprint is made with the key of the day
 * (VisitorKey), one per person, work and day. Each new fingerprint adds one to
 * the day; the next day the fingerprints and the key are deleted
 * (ForgetWorksiteVisitors), and only the number stays. The day is Colombia's.
 *
 * The browser tells of the visit once the page opens: link previews and
 * search engines do not run the page, and one that says it is a robot does
 * not count either.
 */
final class WorksiteVisits
{
    public const TIMEZONE = 'America/Bogota';

    /** Below this, "1 persona ha visto esta obra" would be whoever is looking at it: the public page says nothing. */
    public const PUBLIC_FROM = 10;

    /** Link previews (WhatsApp, the networks), search engines and headless browsers. */
    private const ROBOT = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|headless/i';

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    public function record(Worksite $worksite, ?string $ip, ?string $browser): void
    {
        if (blank($ip) || blank($browser) || preg_match(self::ROBOT, $browser) === 1) {
            return;
        }
        $day = self::today()->toDateString();
        $fingerprint = hash_hmac('sha256', implode("\n", [tenant()->getTenantKey(), $worksite->id, $ip, $browser]), VisitorKey::of($day));

        DB::transaction(function () use ($worksite, $day, $fingerprint) {
            $new = WorksiteVisitor::query()->insertOrIgnore(['worksite_id' => $worksite->id, 'day' => $day, 'fingerprint' => $fingerprint]);
            if ($new === 1) {
                WorksiteVisitDay::query()->upsert(
                    [['worksite_id' => $worksite->id, 'day' => $day, 'visitors' => 1]],
                    ['worksite_id', 'day'],
                    ['visitors' => DB::raw('worksite_visit_days.visitors + 1')],
                );
            }
        });
    }

    /**
     * What "Obras" says of each work: today, the last 30 days (today and the 29 before) and all of them.
     *
     * @param  list<int>  $worksiteIds
     * @return array<int, array{today: int, last_30_days: int, total: int}>
     */
    public function ofWorksites(array $worksiteIds): array
    {
        $today = self::today();
        $rows = WorksiteVisitDay::query()
            ->whereIn('worksite_id', $worksiteIds)
            ->groupBy('worksite_id')
            ->selectRaw('worksite_id, sum(visitors) filter (where day = ?) as today, sum(visitors) filter (where day >= ?) as last_30_days, sum(visitors) as total', [$today->toDateString(), $today->subDays(29)->toDateString()])
            ->get()
            ->keyBy('worksite_id');

        return collect($worksiteIds)->mapWithKeys(fn (int $id) => [$id => [
            'today' => (int) ($rows[$id]->today ?? 0),
            'last_30_days' => (int) ($rows[$id]->last_30_days ?? 0),
            'total' => (int) ($rows[$id]->total ?? 0),
        ]])->all();
    }

    /** What the public page says: all of them, from PUBLIC_FROM people. */
    public function publicTotal(Worksite $worksite): ?int
    {
        $total = (int) WorksiteVisitDay::query()->where('worksite_id', $worksite->id)->sum('visitors');

        return $total >= self::PUBLIC_FROM ? $total : null;
    }
}
