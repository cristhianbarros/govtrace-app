<?php

namespace App\Domain\Worksites;

use Illuminate\Database\Eloquent\Model;

/** It. 69 (US-071): how many people saw the page of a work on a day, in Colombia. What stays. */
class WorksiteVisitDay extends Model
{
    public $timestamps = false;

    protected $fillable = ['worksite_id', 'day', 'visitors'];

    protected $casts = ['day' => 'date:Y-m-d', 'visitors' => 'integer'];
}
