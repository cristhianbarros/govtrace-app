<?php

namespace App\Application\Publication;

use App\Application\Contracts\GetPublicContractCard;
use App\Application\Worksites\WorksiteOfContract;
use App\Domain\Contracts\Contract;
use App\Domain\Geography\Municipality;
use App\Domain\Worksites\Worksite;

/**
 * It. 58 (US-059-LEG): the public page of a contract of the territory that
 * has no worksite sheet yet — its US-017 card, and where the map to propose
 * its location starts.
 */
final class PublicContractView
{
    /** @return array<string, mixed> */
    public function handle(Contract $contract): array
    {
        $sheet = WorksiteOfContract::existingIdFor($contract->secop_contract_id);

        return [
            'contract_id' => $contract->secop_contract_id,
            'worksite_id' => $sheet !== null ? Worksite::query()->whereKey($sheet)->value('public_id') : null,
            'name' => $contract->object,
            'contracts' => [[
                'secop_contract_id' => $contract->secop_contract_id,
                'object' => $contract->object,
                ...(new GetPublicContractCard)->handle($contract),
            ]],
            'seat' => self::seatOf($contract),
        ];
    }

    /**
     * Where the map to propose the location of a work starts: the seat of its
     * contract's municipality or, for a Gobernación's contract, the first one
     * of its department by its DIVIPOLA code — its capital.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public static function seatOf(?Contract $contract): ?array
    {
        if ($contract === null) {
            return null;
        }

        $seat = ($contract->municipality_code !== null
            ? Municipality::query()->find($contract->municipality_code)
            : Municipality::query()->where('department_code', $contract->department_code)->whereNotNull('latitude')->orderBy('code')->first()
        )?->seat();

        return $seat !== null ? ['latitude' => $seat->latitude, 'longitude' => $seat->longitude] : null;
    }
}
