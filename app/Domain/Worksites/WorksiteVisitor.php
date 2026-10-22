<?php

namespace App\Domain\Worksites;

use Illuminate\Database\Eloquent\Model;

/**
 * It. 69 (US-071, R-PRIV-07): one person who saw the page of a work on a day,
 * by a fingerprint made with the key of that day — never their IP or browser.
 * Deleted the next day (ForgetWorksiteVisitors).
 */
class WorksiteVisitor extends Model
{
    public $timestamps = false;

    protected $fillable = ['worksite_id', 'day', 'fingerprint'];

    protected $casts = ['day' => 'date:Y-m-d'];
}
