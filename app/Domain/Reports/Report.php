<?php

namespace App\Domain\Reports;

use App\Domain\Organization\User;
use App\Domain\Reports\Exceptions\EditorialDecisionRejected;
use App\Domain\Reports\Exceptions\EvidenceIsImmutable;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Domain\Worksites\Worksite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * What a veedor sends from the worksite (US-008) — an "evidencia" in the
 * business's words. Lives in the organization's own database, like the
 * worksite it belongs to — the whole worksite, with every contract it
 * groups (R-INT-05).
 *
 * What the veedor sent never changes (R-TA-02); only its editorial status
 * does, and only by the Administrador (US-036, US-037).
 */
class Report extends Model
{
    /** Everything the veedor sent and the server recorded on arrival. */
    private const SENT = [
        'user_id', 'worksite_id', 'classification', 'comment',
        'latitude', 'longitude', 'accuracy_meters', 'geofence_radius_meters',
        'captured_at', 'received_at', 'suspicious_capture_time',
    ];

    protected $fillable = [
        ...self::SENT,
        'editorial_status', 'editorial_reason', 'editorial_decided_at',
    ];

    protected $casts = [
        'classification' => ReportClassification::class,
        'geofence_radius_meters' => 'integer',
        'captured_at' => 'datetime',
        'received_at' => 'datetime',
        'suspicious_capture_time' => 'boolean',
        'editorial_status' => EditorialStatus::class,
        'editorial_decided_at' => 'datetime',
    ];

    protected $attributes = [
        'editorial_status' => 'hidden',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw EvidenceIsImmutable::cannotDelete());

        static::updating(function (self $report) {
            $altered = array_keys($report->getDirty());
            if ($sent = array_values(array_intersect($altered, self::SENT))) {
                throw EvidenceIsImmutable::cannotAlter($sent);
            }
        });
    }

    public function worksite(): BelongsTo
    {
        return $this->belongsTo(Worksite::class);
    }

    /** Its files: 1 to 5 photos or 1 PDF (US-009). */
    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** One Merkle root per report (US-020b). */
    public function seal(): HasOne
    {
        return $this->hasOne(ReportSeal::class);
    }

    /** What the public map shows (US-027 builds its pins on this, it. 24). */
    public function scopeOnPublicMap(Builder $query): void
    {
        $query->where('editorial_status', EditorialStatus::Published);
    }

    /** What the public timeline shows: the published ones, and the withdrawn as tombstones. */
    public function scopeOnPublicTimeline(Builder $query): void
    {
        $query->whereIn('editorial_status', [EditorialStatus::Published, EditorialStatus::Withdrawn]);
    }

    public function publish(): void
    {
        match ($this->editorial_status) {
            EditorialStatus::Hidden => null,
            EditorialStatus::Published => throw EditorialDecisionRejected::becauseOfStatus('La evidencia ya está publicada.'),
            EditorialStatus::Rejected => throw EditorialDecisionRejected::becauseOfStatus('Una evidencia rechazada no se publica.'),
            EditorialStatus::Withdrawn => throw EditorialDecisionRejected::becauseOfStatus('El retiro es definitivo: una evidencia retirada no se vuelve a publicar.'),
        };

        // Lo publicado tiene que poder verificarse (US-024).
        if ($this->seal?->status !== SealStatus::Sealed) {
            throw EditorialDecisionRejected::becauseOfStatus('La evidencia todavía no está sellada: se publica cuando llegue a «Sellada».');
        }

        $this->decide(EditorialStatus::Published, null);
    }

    public function reject(?string $reason): void
    {
        match ($this->editorial_status) {
            EditorialStatus::Hidden => null,
            EditorialStatus::Published => throw EditorialDecisionRejected::becauseOfStatus('Una evidencia publicada no se rechaza: se retira, dejando una lápida.'),
            default => throw EditorialDecisionRejected::becauseOfStatus('Solo se rechaza una evidencia oculta, desde la bandeja de entrada.'),
        };

        $this->decide(EditorialStatus::Rejected, self::requiredReason($reason, 'Rechazar exige un motivo.'));
    }

    public function withdraw(?string $reason): void
    {
        match ($this->editorial_status) {
            EditorialStatus::Published => null,
            EditorialStatus::Hidden => throw EditorialDecisionRejected::becauseOfStatus('Una evidencia nunca publicada no se retira: se rechaza desde la bandeja de entrada.'),
            EditorialStatus::Rejected => throw EditorialDecisionRejected::becauseOfStatus('Una evidencia rechazada nunca se publicó: no hay nada que retirar.'),
            EditorialStatus::Withdrawn => throw EditorialDecisionRejected::becauseOfStatus('La evidencia ya está retirada.'),
        };

        $this->decide(EditorialStatus::Withdrawn, self::requiredReason($reason, 'Retirar exige un motivo.'));
    }

    /**
     * What the Administrador can do with it from the inbox: publishing and
     * rejecting are one evidence at a time — there is no bulk action.
     *
     * @return list<string>
     */
    public function editorialActions(): array
    {
        return match ($this->editorial_status) {
            EditorialStatus::Hidden => ['publish', 'reject'],
            EditorialStatus::Published => ['withdraw'],
            default => [],
        };
    }

    /**
     * R-USR-02: the veedor sees why their report was rejected.
     *
     * @return array{status: string, reason: ?string}
     */
    public function editorialStatusForVeedor(): array
    {
        return [
            'status' => $this->editorial_status->veedorLabel(),
            'reason' => $this->editorial_status === EditorialStatus::Rejected ? $this->editorial_reason : null,
        ];
    }

    private function decide(EditorialStatus $status, ?string $reason): void
    {
        $this->update([
            'editorial_status' => $status,
            'editorial_reason' => $reason,
            'editorial_decided_at' => now(),
        ]);
    }

    private static function requiredReason(?string $reason, string $message): string
    {
        $reason = trim((string) $reason);

        return $reason !== '' ? $reason : throw EditorialDecisionRejected::reasonRequired($message);
    }
}
