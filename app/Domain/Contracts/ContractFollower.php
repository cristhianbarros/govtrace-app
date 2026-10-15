<?php

namespace App\Domain\Contracts;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * It. 59 (US-066): an organization follows a contract — it has a worksite
 * sheet for it — with how many published evidences that sheet has, and when
 * the last one was published. A summary in the central database of what each
 * organization's database says (ContractFollowers keeps it up to date).
 */
class ContractFollower extends Model
{
    use CentralConnection;

    protected $fillable = ['tenant_id', 'secop_contract_id', 'published_evidences', 'last_published_at'];

    protected $casts = [
        'published_evidences' => 'integer',
        'last_published_at' => 'datetime',
    ];
}
