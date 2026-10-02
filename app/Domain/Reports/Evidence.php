<?php

namespace App\Domain\Reports;

use App\Domain\Reports\Exceptions\EvidenceIsImmutable;
use App\Domain\Shared\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file of a report — what gets sealed, verified and downloaded
 * (US-009, US-024, US-026). Stored exactly as the phone sent it.
 */
class Evidence extends Model
{
    use HasPublicId;

    protected $table = 'evidences';

    protected $fillable = ['report_id', 'kind', 'mime_type', 'size_bytes', 'sha256', 'storage_path', 'leaf_index', 'merkle_proof'];

    protected $casts = [
        'size_bytes' => 'integer',
        'leaf_index' => 'integer',
        // R-BLK-05: los hermanos de su hoja en el árbol del reporte (US-020b).
        'merkle_proof' => 'array',
    ];

    /** The file as the phone sent it, and where it's stored: never replaced (R-TA-02). */
    private const FILE = ['report_id', 'kind', 'mime_type', 'size_bytes', 'sha256', 'storage_path'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw EvidenceIsImmutable::cannotDelete());

        static::updating(function (self $evidence) {
            if ($file = array_values(array_intersect(array_keys($evidence->getDirty()), self::FILE))) {
                throw EvidenceIsImmutable::cannotAlter($file);
            }
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** US-026: it downloads by its hash, never by the phone's name — that can carry a person or a place. */
    public function downloadName(): string
    {
        return $this->baseName().'.'.EvidenceKind::from($this->kind)->extension();
    }

    public function proofName(): string
    {
        return $this->baseName().'.prueba.json';
    }

    private function baseName(): string
    {
        return 'evidencia-'.substr($this->sha256, 0, 12);
    }
}
