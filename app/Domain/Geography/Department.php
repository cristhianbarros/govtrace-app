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

    /**
     * The only two departments SECOP II names differently from DIVIPOLA
     * (checked against every "departamento" value of dataset jbjy-vk9h on
     * 2026-09-27); the other 31 match once accents and case are ignored.
     */
    public const SECOP_NAMES = [
        '11' => 'Distrito Capital de Bogotá',
        '88' => 'San Andrés, Providencia y Santa Catalina',
    ];

    /** How SECOP II spells this department in its "departamento" field. */
    public function secopName(): string
    {
        return self::SECOP_NAMES[$this->code] ?? $this->name;
    }

    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class, 'department_code', 'code');
    }
}
