<?php

namespace App\Domain\CitizenReports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * It. 66b (US-069): "Yo también lo vi" — a citizen who joined a citizen
 * report. Its email, encrypted and never shown to the veeduría (R-LEG-10);
 * erased 30 days after the report is answered or documented.
 */
class CitizenReportSupport extends Model
{
    protected $fillable = ['citizen_report_id', 'email', 'email_hash', 'data_authorized_at', 'data_policy_version'];

    protected $hidden = ['email', 'email_hash'];

    protected $casts = [
        'email' => 'encrypted',
        'data_authorized_at' => 'datetime',
    ];

    public function citizenReport(): BelongsTo
    {
        return $this->belongsTo(CitizenReport::class);
    }
}
