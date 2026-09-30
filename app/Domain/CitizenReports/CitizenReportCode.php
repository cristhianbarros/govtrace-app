<?php

namespace App\Domain\CitizenReports;

use Illuminate\Database\Eloquent\Model;

/** US-059-LEG: the 6-digit code that proves a citizen owns the email they give. */
class CitizenReportCode extends Model
{
    public const VALID_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['email_hash', 'code_hash', 'attempts', 'expires_at', 'used_at', 'data_authorized_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'data_authorized_at' => 'datetime',
    ];
}
