<?php

namespace App\Application\Publication;

use App\Application\Contracts\GetPublicContractCard;
use App\Domain\Contracts\Contract;
use App\Domain\Worksites\Worksite;

/**
 * US-029: what the map asks for when a pin is clicked (R-MAP-02) — the
 * contracts of the worksite, each with its US-017 card, and its public
 * timeline. Also for a worksite not anchored yet, like a new grouping
 * (US-045-INT): it has no pin, but its contracts are public anyway.
 */
class PublicWorksiteView
{
    /** @return array<string, mixed> */
    public function handle(Worksite $worksite): array
    {
        $contracts = Contract::query()
            ->whereIn('secop_contract_id', $worksite->contracts()->pluck('secop_contract_id'))
            ->orderBy('secop_contract_id')
            ->get();
        $card = new GetPublicContractCard;

        return [
            'id' => $worksite->public_id, // it. 46c
            // Sin nombre (una ficha de un solo contrato), el objeto del contrato.
            'name' => $worksite->name ?? $contracts->first()?->object,
            'contracts' => $contracts->map(fn (Contract $contract) => [
                'secop_contract_id' => $contract->secop_contract_id,
                'object' => $contract->object,
                ...$card->handle($contract),
            ])->all(),
            'timeline' => (new PublicTimeline)->handle($worksite->id),
            // It. 40b: el color de su pin y por qué, en palabras.
            'condition' => (new WorksiteCondition)->of($worksite, $contracts),
        ];
    }
}
