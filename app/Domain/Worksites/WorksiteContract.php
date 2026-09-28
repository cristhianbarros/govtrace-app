<?php

namespace App\Domain\Worksites;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One SECOP contract grouped in a worksite (R-INT-05). Within an
 * organization, a contract belongs to a single worksite — the unique
 * index on secop_contract_id guarantees it.
 */
class WorksiteContract extends Model
{
    protected $fillable = ['worksite_id', 'secop_contract_id'];

    public function worksite(): BelongsTo
    {
        return $this->belongsTo(Worksite::class);
    }
}
