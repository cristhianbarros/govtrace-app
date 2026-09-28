<?php

namespace App\Domain\Reports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file of a report — what gets sealed, verified and downloaded
 * (US-009, US-024, US-026). Stored exactly as the phone sent it.
 */
class Evidence extends Model
{
    protected $table = 'evidences';

    protected $fillable = ['report_id', 'kind', 'mime_type', 'size_bytes', 'sha256', 'storage_path', 'leaf_index', 'merkle_proof'];

    protected $casts = [
        'size_bytes' => 'integer',
        'leaf_index' => 'integer',
        // R-BLK-05: los hermanos de su hoja en el árbol del reporte (US-020b).
        'merkle_proof' => 'array',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
