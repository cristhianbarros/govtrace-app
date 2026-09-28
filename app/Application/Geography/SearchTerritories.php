<?php

namespace App\Application\Geography;

use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\MunicipalityMatcher;
use App\Domain\Geography\PlaceName;

/**
 * US-012: the territory picker finds departments and municipalities of the
 * DIVIPOLA table by name, ignoring accents and case ("Cienaga" finds
 * "Ciénaga"), from 3 characters. The whole table is ~1.100 rows, so it's
 * filtered in memory with the same normalization as the SECOP matcher.
 */
class SearchTerritories
{
    private const MIN_CHARACTERS = 3;

    private const MAX_RESULTS = 20;

    /** @return list<array{code: string, name: string, kind: string}> */
    public function handle(string $keyword): array
    {
        $matcher = new MunicipalityMatcher;
        $needle = $matcher->normalize($keyword);

        if (mb_strlen($needle) < self::MIN_CHARACTERS) {
            return [];
        }

        $matches = fn (string $name) => str_contains($matcher->normalize($name), $needle);

        $departments = Department::query()->orderBy('name')->get()
            ->filter(fn (Department $department) => $matches($department->name))
            ->map(fn (Department $department) => self::department($department));

        $municipalities = Municipality::query()->with('department')->orderBy('name')->get()
            ->filter(fn (Municipality $municipality) => $matches($municipality->name))
            ->map(fn (Municipality $municipality) => self::municipality($municipality));

        return $departments->concat($municipalities)->take(self::MAX_RESULTS)->values()->all();
    }

    /** @return array{code: string, name: string, kind: string} */
    public static function department(Department $department): array
    {
        return ['code' => $department->code, 'name' => PlaceName::forDisplay($department->name), 'kind' => 'Departamento'];
    }

    /** @return array{code: string, name: string, kind: string} */
    public static function municipality(Municipality $municipality): array
    {
        $name = PlaceName::forDisplay($municipality->name);
        $department = PlaceName::forDisplay($municipality->department->name);

        return ['code' => $municipality->code, 'name' => "{$name} ({$department})", 'kind' => 'Municipio'];
    }
}
