<?php

namespace App\Domain\Contracts;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One execution of the SECOP sync job — what US-014's health panel
 * (it. 22) reads. US-013 only writes it.
 */
class SecopSyncRun extends Model
{
    use CentralConnection;

    protected $fillable = [
        'organization_id', 'started_at', 'finished_at', 'status',
        'contracts_inserted', 'contracts_updated', 'contracts_discarded',
        'unmatched_locations', 'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'unmatched_locations' => 'array',
    ];
}
