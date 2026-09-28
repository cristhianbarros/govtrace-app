<?php

namespace App\Domain\Geography;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A DIVIPOLA department (DANE). Central reference data, shared by every
 * tenant — see specs/SPEC.md "Contratos SECOP II" and R-INT-03.
 */
class Department extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name'];

    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class, 'department_code', 'code');
    }
}
