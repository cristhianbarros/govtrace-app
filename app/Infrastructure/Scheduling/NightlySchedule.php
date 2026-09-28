<?php

namespace App\Infrastructure\Scheduling;

use App\Domain\Configuration\Parameters;
use App\Jobs\CalculateWorksitesAtRisk;
use App\Jobs\SyncSecopContracts;
use Closure;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The night's work (US-013, US-034). The sync hour comes from its
 * parameter (US-038-CFG) every time the schedule is built — and
 * schedule:work runs schedule:run as a NEW process every minute —, so a
 * change applies from the next minute, without restarting anything.
 */
final class NightlySchedule
{
    private const DEFAULT_SYNC_HOUR = '02:00';

    /**
     * @param  (Closure(): ?string)|null  $syncHour  for tests; by default, the parameter
     */
    public static function register(Schedule $schedule, ?Closure $syncHour = null): void
    {
        // The parameters table doesn't exist yet during the first `migrate`
        // of a new database, and routes/console.php loads for every command.
        try {
            $hour = ($syncHour ?? fn () => Parameters::current('secop_sync_hour'))() ?? self::DEFAULT_SYNC_HOUR;
        } catch (Throwable) {
            $hour = self::DEFAULT_SYNC_HOUR;
        }

        $schedule->job(new SyncSecopContracts)
            ->dailyAt($hour)
            ->name('secop-sync-nightly')
            ->onOneServer();

        // Una hora después de la sincronización: el riesgo se calcula con los
        // contratos ya actualizados, no con los del día anterior.
        $schedule->job(new CalculateWorksitesAtRisk)
            ->dailyAt(Carbon::createFromFormat('H:i', $hour)->addHour()->format('H:i'))
            ->name('calculate-worksites-at-risk')
            ->onOneServer();
    }
}
