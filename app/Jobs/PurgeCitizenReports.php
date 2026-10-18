<?php

namespace App\Jobs;

use App\Domain\Audit\AuditLog;
use App\Domain\CitizenReports\CitizenReport;
use App\Domain\CitizenReports\CitizenReportSupport;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;

/**
 * It. 45c (D-V2-09): the retention of the reports of citizens (US-059-LEG).
 * Their email only serves to answer them (R-LEG-10). Every day, in each
 * organization: 30 days after a report was answered, its email is erased and
 * the report stays; 30 days after it was discarded, it is deleted, with its
 * photo. A report not yet handled keeps its email: it still has to be
 * answered. The audit log says how many, never an email.
 */
class PurgeCitizenReports implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const RETENTION_DAYS = 30;

    public function handle(): void
    {
        $limit = now()->subDays(self::RETENTION_DAYS);

        foreach (Tenant::query()->get() as $tenant) {
            [$deleted, $erased] = $tenant->run(function () use ($limit) {
                $discarded = CitizenReport::query()->where('status', 'discarded')->where('handled_at', '<', $limit)->get();
                foreach ($discarded as $report) {
                    if ($report->photo_paths !== []) {
                        Storage::disk('evidencias')->delete($report->photo_paths);
                    }
                    $report->delete();
                }

                // It. 66b: también un informe documentado (la 65 lo olvidó), y los correos de quienes se sumaron.
                $handled = CitizenReport::query()->whereIn('status', ['answered', 'documented'])->where('handled_at', '<', $limit);
                CitizenReportSupport::query()->whereIn('citizen_report_id', (clone $handled)->select('id'))->whereNotNull('email')
                    ->update(['email' => null, 'email_hash' => null]);
                $erased = (clone $handled)->whereNotNull('email')->update(['email' => null, 'email_hash' => null]);

                return [$discarded->count(), $erased];
            });

            if ($deleted + $erased > 0) {
                AuditLog::record(
                    action: 'citizen_reports.purged',
                    organizationId: $tenant->id,
                    actorType: 'system',
                    after: ['reports_deleted' => $deleted, 'emails_erased' => $erased],
                );
            }
        }
    }
}
