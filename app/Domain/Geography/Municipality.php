<?php

namespace App\Domain\Geography;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A DIVIPOLA municipality (DANE). Central reference data — see
 * MunicipalityMatcher for how a free-text SECOP II name resolves to one
 * of these rows (US-013, R-INT-03).
 */
class Municipality extends Model
{
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
}
