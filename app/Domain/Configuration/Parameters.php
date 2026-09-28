<?php

namespace App\Domain\Configuration;

use Illuminate\Support\Carbon;

/**
 * Read side of the operational parameters (US-038-CFG). R-AUD-05: "los
 * reportes se validan con el valor vigente en el momento de la captura",
 * so callers that need to be consistent with a PAST moment (not "now")
 * pass $at explicitly — see specs/PLAN.md it. 10's geofence radius check.
 */
class Parameters
{
    public static function valueAt(string $key, ?Carbon $at = null): ?string
    {
        return ParameterValue::query()
            ->where('key', $key)
            ->where('effective_from', '<=', $at ?? now())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->value('value');
    }

    public static function current(string $key): ?string
    {
        return self::valueAt($key);
    }

    /**
     * Inserts a NEW version — never updates a row in place, or querying
     * "the value at a past moment" would stop being possible.
     */
    public static function set(string $key, string $value, ?Carbon $effectiveFrom = null): ParameterValue
    {
        return ParameterValue::create([
            'key' => $key,
            'value' => $value,
            'effective_from' => $effectiveFrom ?? now(),
        ]);
    }
}
