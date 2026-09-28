<?php

namespace App\Application\Publication;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\User;
use App\Domain\Reports\Report;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * US-036 / US-037: the Administrador de Organización publishes, rejects or
 * withdraws an evidence of THEIR organization — one at a time. The rules
 * are the Report's; here each decision is applied under a row lock (two
 * Administradores deciding at once can't both win) and recorded in the
 * audit log (R-AUD-04): who, when, the status before and after, and the
 * reason. If the log fails, the decision isn't applied.
 */
class EditorialDecisions
{
    public function publish(User $administrator, int $reportId): Report
    {
        return $this->decide($administrator, $reportId, 'evidence.published', fn (Report $report) => $report->publish());
    }

    public function reject(User $administrator, int $reportId, ?string $reason): Report
    {
        return $this->decide($administrator, $reportId, 'evidence.rejected', fn (Report $report) => $report->reject($reason));
    }

    public function withdraw(User $administrator, int $reportId, ?string $reason): Report
    {
        return $this->decide($administrator, $reportId, 'evidence.withdrawn', fn (Report $report) => $report->withdraw($reason));
    }

    /** @param  Closure(Report): void  $decision */
    private function decide(User $administrator, int $reportId, string $action, Closure $decision): Report
    {
        return DB::transaction(function () use ($administrator, $reportId, $action, $decision) {
            $report = Report::query()->lockForUpdate()->findOrFail($reportId);
            $before = $report->editorial_status;

            $decision($report);

            AuditLog::record(
                action: $action,
                organizationId: tenant()->getTenantKey(),
                actorType: 'organization_admin',
                actorId: (string) $administrator->id,
                actorName: $administrator->name,
                before: ['report_id' => $report->id, 'editorial_status' => $before->value],
                after: array_filter([
                    'report_id' => $report->id,
                    'editorial_status' => $report->editorial_status->value,
                    'reason' => $report->editorial_reason,
                ], fn ($value) => $value !== null),
            );

            return $report;
        });
    }
}
