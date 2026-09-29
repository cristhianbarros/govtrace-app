<?php

namespace App\Application\Publication;

use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;

/**
 * The cards of a worksite's public timeline, newest first: what each
 * organization publishes on its own map (R-MAP-01). The it. 24 serves it
 * (US-029) and adds the approximate coordinates (R-PRIV-02).
 *
 * A withdrawn evidence stays as a tombstone (US-037): without its files
 * or its comment, but with its seal, for external audit. Hidden and
 * rejected evidences never show.
 */
class PublicTimeline
{
    public const TOMBSTONE_NOTICE = '🚫 Evidencia retirada por la organización por incumplimiento de políticas.';

    /** @return list<array<string, mixed>> */
    public function handle(int $worksiteId): array
    {
        return Report::query()
            ->where('worksite_id', $worksiteId)
            ->onPublicTimeline()
            ->with(['seal', 'evidences' => fn ($evidences) => $evidences->orderBy('id')])
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Report $report) => $this->card($report))
            ->all();
    }

    /** @return array<string, mixed> */
    private function card(Report $report): array
    {
        $card = [
            'report_id' => $report->id,
            'classification' => $report->classification->value,
            'captured_at' => $report->captured_at->toIso8601String(),
        ];

        $content = $report->editorial_status === EditorialStatus::Withdrawn
            ? ['notice' => self::TOMBSTONE_NOTICE]
            : [
                'comment' => $report->comment,
                // US-026: cada archivo, con su descarga y su prueba de inclusión.
                'files' => $report->evidences->map(fn (Evidence $evidence) => [
                    'id' => $evidence->id,
                    'kind' => $evidence->kind,
                    'sha256' => $evidence->sha256,
                    'download_url' => "/public/evidences/{$evidence->id}/download",
                    'proof_url' => "/public/evidences/{$evidence->id}/proof",
                ])->all(),
            ];

        return [
            ...$card,
            ...$content,
            'seal' => $report->seal->only(['merkle_root', 'tx_hash', 'ledger']),
            // US-025: también el de una retirada, para auditoría.
            'receipt_url' => "/public/reports/{$report->id}/receipt",
        ];
    }
}
