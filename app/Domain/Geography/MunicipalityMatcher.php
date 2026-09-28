<?php

namespace App\Domain\Geography;

use Illuminate\Support\Str;

/**
 * Matches the free-text municipality name that SECOP II sends in a contract
 * against the DIVIPOLA table, normalizing accents and case so "Cienaga",
 * "Ciénaga" and "CIÉNAGA" all resolve to the same municipality (US-013,
 * R-INT-03). A name that matches nothing returns null — the sync job
 * decides what to do with that (discard and report, see US-014).
 */
class MunicipalityMatcher
{
    public function match(string $secopName): ?Municipality
    {
        $needle = $this->normalize($secopName);

        return Municipality::query()
            ->get(['code', 'name', 'department_code'])
            ->first(fn (Municipality $municipality) => $this->normalize($municipality->name) === $needle);
    }

    public function normalize(string $name): string
    {
        return Str::of($name)->ascii()->upper()->squish()->toString();
    }
}
