<?php

namespace App\Domain\Configuration;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One version of one parameter (US-038-CFG). Never updated in place — a
 * change inserts a NEW row with its own effective_from, which is what
 * makes {@see Parameters::valueAt()} possible (R-AUD-05).
 */
class ParameterValue extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    protected $table = 'parameters';

    protected $fillable = ['key', 'value', 'effective_from'];

    protected $casts = [
        'effective_from' => 'datetime',
        'created_at' => 'datetime',
    ];
}
