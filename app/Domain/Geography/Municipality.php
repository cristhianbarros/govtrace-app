<?php

namespace App\Domain\Geography;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A DIVIPOLA municipality (DANE). Central reference data — see
 * MunicipalityMatcher for how a free-text SECOP II name resolves to one
 * of these rows (US-013, R-INT-03).
 */
class Municipality extends Model
{
    use CentralConnection;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name', 'department_code', 'latitude', 'longitude'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_code', 'code');
    }

    /** Where its seat (cabecera) is, as DIVIPOLA gives it; null if it doesn't say. */
    public function seat(): ?GeoPoint
    {
        return $this->latitude !== null && $this->longitude !== null ? new GeoPoint($this->latitude, $this->longitude) : null;
    }

    /**
     * It. 46f (R-GEO-01): the seat a worksite is measured from — that of its
     * contract's municipality or, for a departmental contract (a
     * Gobernación's, with no municipality), the nearest one of its department.
     */
    public static function seatFor(?string $municipalityCode, ?string $departmentCode, GeoPoint $near): ?self
    {
        if ($municipalityCode !== null) {
            $own = self::query()->find($municipalityCode);

            return $own?->seat() !== null ? $own : null;
        }

        return self::query()
            ->where('department_code', $departmentCode)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->sortBy(fn (self $municipality) => $municipality->seat()->distanceInMetersTo($near))
            ->first();
    }
}
