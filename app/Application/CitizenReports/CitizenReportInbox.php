<?php

namespace App\Application\CitizenReports;

use App\Domain\Audit\AuditLog;
use App\Domain\CitizenReports\CitizenReport;
use App\Domain\CitizenReports\Notifications\CitizenReportAnswered;
use App\Domain\Organization\User;
use DomainException;
use Illuminate\Support\Facades\Notification;

/**
 * US-059-LEG: the reports citizens send, as the Administrador sees them —
 * without the email of whoever sent them (R-LEG-10) — to answer or discard.
 * The answer reaches the citizen through GovTrace. Both go to the audit log.
 *
 * Inside the organization.
 */
class CitizenReportInbox
{
    /** @return list<array<string, mixed>> newest first */
    public function list(): array
    {
        return CitizenReport::query()->with('worksite')->latest('id')->get()
            ->map(fn (CitizenReport $report) => [
                'id' => $report->id,
                'worksite_id' => $report->worksite_id,
                'worksite' => CitizenReportDesk::nameOf($report->worksite),
                'received_at' => $report->created_at->toIso8601String(),
                'message' => $report->message,
                'photo_url' => $report->photo_path ? "/citizen-reports/{$report->id}/photo" : null,
                'status' => $report->status,
                'status_label' => CitizenReport::STATUS_LABELS[$report->status],
                'answer' => $report->answer,
            ])->all();
    }

    public function answer(User $administrator, int $id, string $answer): void
    {
        $report = $this->pending($id);
        $report->update(['status' => 'answered', 'answer' => trim($answer), 'handled_at' => now(), 'handled_by' => $administrator->id]);

        Notification::route('mail', $report->email)->notify(new CitizenReportAnswered($report->id, tenant()->displayName(), $report->answer));
        $this->audit('citizen_report.answered', $administrator, $report);
    }

    public function discard(User $administrator, int $id): void
    {
        $report = $this->pending($id);
        $report->update(['status' => 'discarded', 'handled_at' => now(), 'handled_by' => $administrator->id]);

        $this->audit('citizen_report.discarded', $administrator, $report);
    }

    private function pending(int $id): CitizenReport
    {
        $report = CitizenReport::query()->findOrFail($id);
        if ($report->status !== 'new') {
            throw new DomainException('Este informe ya fue atendido o descartado.');
        }

        return $report;
    }

    /** Never the email: the log says which report and which worksite. */
    private function audit(string $action, User $administrator, CitizenReport $report): void
    {
        AuditLog::record(
            action: $action,
            organizationId: tenant()->getTenantKey(),
            actorType: 'organization_admin',
            actorId: (string) $administrator->id,
            actorName: $administrator->name,
            after: ['citizen_report_id' => $report->id, 'worksite_id' => $report->worksite_id, 'status' => $report->status],
        );
    }
}
