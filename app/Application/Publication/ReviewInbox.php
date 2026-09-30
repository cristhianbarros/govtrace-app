<?php

namespace App\Application\Publication;

use App\Domain\Contracts\Contract;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\PlaceName;
use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Domain\Sealing\SealStatus;
use App\Domain\Worksites\Worksite;
use Illuminate\Support\Collection;

/**
 * US-036: the Administrador's inbox — the organization's hidden evidences,
 * oldest first, each with its own actions (no bulk publishing). An
 * evidence arrives here once it's sealed: what gets published has to be
 * verifiable (US-024). The suspicious capture time mark is shown, not
 * acted upon (R-SEC-05, R-MON-02): the Administrador decides.
 *
 * With Published, the ones that can be withdrawn (US-037).
 *
 * Each one says which worksite it is about (its name and municipality) and
 * which veedor sent it (it. 40b): a responsible review needs that context.
 */
class ReviewInbox
{
    /** @return list<array<string, mixed>> */
    public function handle(EditorialStatus $status = EditorialStatus::Hidden): array
    {
        $reports = Report::query()
            ->where('editorial_status', $status)
            ->whereHas('seal', fn ($seal) => $seal->where('status', SealStatus::Sealed))
            ->with(['seal', 'user', 'worksite.contracts', 'evidences' => fn ($evidences) => $evidences->orderBy('id')])
            ->orderBy('id')
            ->get();
        $worksites = $this->describe($reports->pluck('worksite')->filter()->unique('id'));

        return $reports
            ->map(fn (Report $report) => [
                'id' => $report->id,
                'worksite_id' => $report->worksite_id,
                'worksite' => $worksites[$report->worksite_id] ?? null,
                'observer' => $report->user?->name,
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

    /**
     * Each worksite's name (its own, or its first contract's object) and the
     * municipality of that contract, with two queries for the whole inbox.
     *
     * @param  Collection<int, Worksite>  $worksites
     * @return array<int, array{id: int, name: ?string, municipality: ?string}>
     */
    private function describe(Collection $worksites): array
    {
        $contracts = Contract::query()
            ->whereIn('secop_contract_id', $worksites->flatMap->contracts->pluck('secop_contract_id'))
            ->get(['secop_contract_id', 'object', 'municipality_code'])
            ->keyBy('secop_contract_id');
        $municipalities = Municipality::query()->whereIn('code', $contracts->pluck('municipality_code')->filter())->pluck('name', 'code');

        return $worksites->mapWithKeys(function (Worksite $worksite) use ($contracts, $municipalities) {
            $first = $contracts->get($worksite->contracts->sortBy('id')->first()?->secop_contract_id);
            $municipality = $municipalities->get($first?->municipality_code);

            return [$worksite->id => [
                'id' => $worksite->id,
                'name' => $worksite->name ?? $first?->object,
                'municipality' => $municipality !== null ? PlaceName::forDisplay($municipality) : null,
            ]];
        })->all();
    }
}
