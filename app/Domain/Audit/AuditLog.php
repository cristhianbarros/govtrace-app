<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Exceptions\AuditLogIsAppendOnly;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * R-AUD-04: quién, cuándo, qué acción, valor anterior y nuevo. Escrito
 * desde código central (US-011) y, en iteraciones posteriores, desde
 * dentro de cada tenant (publicar/rechazar evidencia, invitar veedor…) —
 * siempre en la base central, para que el Super Administrador pueda
 * consultar todo sin abrir cada base de datos (US-043-MON, it. 21).
 *
 * R-MNT-03: se conserva para siempre — una entrada no se edita ni se
 * borra (it. 36).
 */
class AuditLog extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw AuditLogIsAppendOnly::make());
        static::deleting(fn () => throw AuditLogIsAppendOnly::make());
    }

    protected $fillable = [
        'organization_id', 'actor_type', 'actor_id', 'actor_name',
        'action', 'before', 'after',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(
        string $action,
        ?string $organizationId = null,
        ?string $actorType = null,
        ?string $actorId = null,
        ?string $actorName = null,
        ?array $before = null,
        ?array $after = null,
    ): self {
        return self::create([
            'organization_id' => $organizationId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
