<?php

namespace App\Domain\Reports;

use App\Domain\Organization\User;
use App\Domain\Worksites\Worksite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a veedor sends from the worksite (US-008). Lives in the
 * organization's own database, like the worksite it belongs to — the
 * whole worksite, with every contract it groups (R-INT-05).
 */
class Report extends Model
{
    protected $fillable = [
        'user_id', 'worksite_id', 'classification', 'comment',
        'latitude', 'longitude', 'accuracy_meters', 'geofence_radius_meters',
        'captured_at', 'received_at', 'suspicious_capture_time',
    ];

    protected $casts = [
        'classification' => ReportClassification::class,
        'geofence_radius_meters' => 'integer',
        'captured_at' => 'datetime',
        'received_at' => 'datetime',
        'suspicious_capture_time' => 'boolean',
    ];

    public function worksite(): BelongsTo
    {
        return $this->belongsTo(Worksite::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
