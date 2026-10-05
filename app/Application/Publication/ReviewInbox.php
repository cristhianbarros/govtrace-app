<?php

namespace App\Application\Publication;

use App\Domain\Contracts\Contract;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\PlaceName;
use App\Domain\Reports\Blurring;
use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Domain\Sealing\SealStatus;
use App\Domain\Worksites\PendingLocation;
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
 *
 * And where it was taken (it. 45f): the one that fixed the official location
 * of its worksite (First-Touch, R-GEO-01) comes marked, with that point —
 * the worksite's —; the others, how far from the worksite they were taken,
 * never the veedor's coordinates.
 *
 * It. 46f: a first report that did not fix the location (far from its
 * municipality, or with a poor signal) comes "to confirm", with why and the
 * point it proposes for the worksite.
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
                // It. 46c (US-064-SEC): identificadores públicos.
                'id' => $report->public_id,
                'worksite_id' => $report->worksite?->public_id,
                'worksite' => $worksites[$report->worksite_id] ?? null,
                'observer' => $report->user?->name,
                'classification' => $report->classification->value,
                'comment' => $report->comment,
                'captured_at' => $report->captured_at->toIso8601String(),
                'received_at' => $report->received_at->toIso8601String(),
                'suspicious_capture_time' => $report->suspicious_capture_time,
                'files' => $report->evidences->map(fn (Evidence $evidence) => [
                    'id' => $evidence->public_id,
                    'kind' => $evidence->kind,
                    'sha256' => $evidence->sha256,
                    // It. 46e (R-PRIV-05): lo que se difuminó en el celular; null si se envió antes.
                    'blurring' => Blurring::presentOf($evidence),
                ])->all(),
                'seal' => $report->seal->only(['merkle_root', 'tx_hash', 'ledger']),
                'location' => $this->location($report),
                'actions' => $report->editorialActions(),
            ])
            ->all();
    }

    /**
     * Where the report was taken, as the server recorded it on arrival. Only
     * the first report of the worksite brings its point, which is (or was
     * proposed as) the worksite's; and whether the location was corrected or
     * confirmed afterwards (US-035).
     *
     * @return array{anchored_worksite: bool, distance_meters: ?int, point: ?array{latitude: float, longitude: float}, corrected: bool, pending: ?array{reason: string, status: string}}
     */
    private function location(Report $report): array
    {
        $pending = PendingLocation::reason($report);

        if (! $report->anchored_worksite && $pending === null) {
            return ['anchored_worksite' => false, 'distance_meters' => $report->distance_to_worksite_meters, 'point' => null, 'corrected' => false, 'pending' => null];
        }

        $point = ['latitude' => round((float) $report->latitude, 7), 'longitude' => round((float) $report->longitude, 7)];
        $official = $report->worksite?->location();
        $atThisPoint = $official !== null && [round($official->latitude, 7), round($official->longitude, 7)] === array_values($point);

        return [
            'anchored_worksite' => $report->anchored_worksite,
            'distance_meters' => $report->distance_to_worksite_meters,
            'point' => $point,
            'corrected' => $report->anchored_worksite && ! $atThisPoint,
            'pending' => $pending === null ? null : [
                'reason' => $pending,
                // unlocated: the worksite still has no location; confirmed: it was fixed here; elsewhere: somewhere else.
                'status' => match (true) {
                    $official === null => 'unlocated',
                    $atThisPoint => 'confirmed',
                    default => 'elsewhere',
                },
            ],
        ];
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
                'id' => $worksite->public_id,
                'name' => $worksite->name ?? $first?->object,
                'municipality' => $municipality !== null ? PlaceName::forDisplay($municipality) : null,
            ]];
        })->all();
    }
}
