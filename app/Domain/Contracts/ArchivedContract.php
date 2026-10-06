<?php

namespace App\Domain\Contracts;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A contract moved out of "contracts" by the monthly archive (US-048-MNT,
 * R-MNT-04), exactly as it was, with its same id. Only ContractArchive
 * moves rows in and out.
 */
class ArchivedContract extends Model
{
    use CentralConnection;

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'value' => 'decimal:2',
        'signed_at' => 'date',
        'end_date' => 'date',
        'raw_payload' => 'array',
        'funding_sources' => 'array',
        'archived_at' => 'datetime',
    ];
}
