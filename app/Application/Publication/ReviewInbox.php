<?php

namespace App\Application\Publication;

use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Domain\Sealing\SealStatus;

/**
 * US-036: the Administrador's inbox — the organization's hidden evidences,
 * oldest first, each with its own actions (no bulk publishing). An
 * evidence arrives here once it's sealed: what gets published has to be
 * verifiable (US-024). The suspicious capture time mark is shown, not
 * acted upon (R-SEC-05, R-MON-02): the Administrador decides.
 */
class ReviewInbox
{
    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        return Report::query()
            ->where('editorial_status', EditorialStatus::Hidden)
            ->whereHas('seal', fn ($seal) => $seal->where('status', SealStatus::Sealed))
            ->with(['seal', 'evidences' => fn ($evidences) => $evidences->orderBy('id')])
            ->orderBy('id')
            ->get()
            ->map(fn (Report $report) => [
                'id' => $report->id,
                'worksite_id' => $report->worksite_id,
                'classification' => $report->classification->value,
                'comment' => $report->comment,
                'captured_at' => $report->captured_at->toIso8601String(),
                'received_at' => $report->received_at->toIso8601String(),
                'suspicious_capture_time' => $report->suspicious_capture_time,
                'files' => $report->evidences->map(fn (Evidence $evidence) => [
                    'id' => $evidence->id,
                    'kind' => $evidence->kind,
                    'sha256' => $evidence->sha256,
                ])->all(),
                'seal' => $report->seal->only(['merkle_root', 'tx_hash', 'ledger']),
                'actions' => $report->editorialActions(),
            ])
            ->all();
    }
}
