<?php

namespace App\Application\Worksites;

use App\Domain\Contracts\ContractFollower;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;

/**
 * It. 59 (US-066): keeps the central summary of who follows what
 * (ContractFollower) in step with the organization's worksite sheets — when
 * a sheet is born, when an evidence is published, rejected or withdrawn, and
 * when contracts are grouped. The organization's database is the truth: if
 * a write here fails after it committed, the nightly recount
 * (contracts:followers) puts it right.
 *
 * Inside the organization.
 */
final class ContractFollowers
{
    /** The sheet's contracts, with what the sheet has published. */
    public static function refresh(Worksite $worksite): void
    {
        $published = Report::query()->onPublicMap()->where('worksite_id', $worksite->id);
        self::save($worksite->contracts()->pluck('secop_contract_id')->all(), (clone $published)->count(), (clone $published)->max('editorial_decided_at'));
    }

    /** The whole organization, from scratch: every sheet's contracts, and nothing else. */
    public static function refreshOrganization(): void
    {
        $stats = Report::query()
            ->onPublicMap()
            ->selectRaw('worksite_id, count(*) as total, max(editorial_decided_at) as last_published_at')
            ->groupBy('worksite_id')
            ->get()
            ->keyBy('worksite_id');
        $links = WorksiteContract::query()->get(['worksite_id', 'secop_contract_id']);

        ContractFollower::query()->getConnection()->transaction(function () use ($stats, $links) {
            ContractFollower::query()
                ->where('tenant_id', tenant()->getTenantKey())
                ->whereNotIn('secop_contract_id', $links->pluck('secop_contract_id'))
                ->delete();

            foreach ($links->groupBy('worksite_id') as $worksiteId => $sheetLinks) {
                $stat = $stats->get($worksiteId);
                self::save($sheetLinks->pluck('secop_contract_id')->all(), (int) ($stat->total ?? 0), $stat?->last_published_at);
            }
        });
    }

    /** @param  list<string>  $secopContractIds */
    private static function save(array $secopContractIds, int $publishedEvidences, mixed $lastPublishedAt): void
    {
        if ($secopContractIds === []) {
            return;
        }

        $now = now();
        ContractFollower::query()->upsert(
            array_map(fn (string $secopContractId) => [
                'tenant_id' => tenant()->getTenantKey(),
                'secop_contract_id' => $secopContractId,
                'published_evidences' => $publishedEvidences,
                'last_published_at' => $lastPublishedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ], $secopContractIds),
            ['tenant_id', 'secop_contract_id'],
            ['published_evidences', 'last_published_at', 'updated_at'],
        );
    }
}
