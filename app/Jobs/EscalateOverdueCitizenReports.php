<?php

namespace App\Jobs;

use App\Application\CitizenReports\CitizenReportDesk;
use App\Application\CitizenReports\CitizenResponseDeadline;
use App\Domain\CitizenReports\CitizenReport;
use App\Domain\CitizenReports\Notifications\CitizenReportOverdue;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\OrganizationStatus;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * It. 66a (US-069, R-LEG-12): each morning, the citizen of a report the
 * veeduría did not act on in time (no answer, no visit asked, not discarded)
 * gets the way to the Contraloría. Once. Only active organizations.
 */
class EscalateOverdueCitizenReports implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        foreach (Tenant::query()->where('status', OrganizationStatus::Active->value)->get() as $tenant) {
            $tenant->run(function () use ($tenant) {
                $overdue = CitizenResponseDeadline::overdue()->whereNull('escalated_at')->with('worksite.contracts')->get();
                foreach ($overdue as $report) {
                    $this->escalate($tenant, $report);
                }
            });
        }
    }

    private function escalate(Tenant $tenant, CitizenReport $report): void
    {
        $worksite = $report->worksite;
        $contract = Contract::query()->whereIn('secop_contract_id', $worksite->contracts->pluck('secop_contract_id'))->orderBy('secop_contract_id')->first();
        $mail = new CitizenReportOverdue($report->reference(), $tenant->displayName(), CitizenReportDesk::nameOf($worksite), $contract?->secop_contract_id, $contract?->entity_name, $report->message);

        // It. 66b: a quien informó y a quienes se sumaron.
        foreach ($report->recipients() as $email) {
            Notification::route('mail', $email)->notify($mail);
        }
        $report->update(['escalated_at' => now()]);
    }
}
