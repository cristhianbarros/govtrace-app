<?php

namespace App\Application\Worksites;

use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The worksite sheet that groups a contract, inside the organization. It is
 * born without location the first time someone reports the contract: a
 * veedor (US-008) or, since it. 58, a citizen (US-059-LEG). Against two that
 * arrive at once, the link of a contract is unique: the second one to insert
 * it uses the first one's sheet.
 */
final class WorksiteOfContract
{
    /** The id of the sheet of the contract, created if it has none yet. */
    public static function idFor(string $secopContractId): int
    {
        $existing = self::existingIdFor($secopContractId);
        if ($existing !== null) {
            return $existing;
        }

        try {
            // Savepoint: si falla, solo se deshace esta creación.
            return DB::transaction(function () use ($secopContractId) {
                $worksite = Worksite::create();
                $worksite->contracts()->create(['secop_contract_id' => $secopContractId]);

                return $worksite->id;
            });
        } catch (UniqueConstraintViolationException) {
            // Otro creó la ficha de este contrato al mismo tiempo: se usa la suya.
            return WorksiteContract::query()->where('secop_contract_id', $secopContractId)->firstOrFail()->worksite_id;
        }
    }

    /** The id of the sheet of the contract, or null if nobody has reported it yet. */
    public static function existingIdFor(string $secopContractId): ?int
    {
        return WorksiteContract::query()->where('secop_contract_id', $secopContractId)->first()?->worksite_id;
    }
}
