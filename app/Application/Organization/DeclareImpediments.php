<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;

/**
 * US-057-LEG: un veedor declara que no está en ninguno de los impedimentos
 * del artículo 19 de la Ley 850 de 2003 (ser contratista, interventor o
 * trabajador de la obra, o familiar suyo; servidor público relacionado…).
 * GovTrace no puede comprobarlo: guarda la declaración con su fecha y la
 * deja en el log de auditoría (R-LEG-05). Sin ella no recibe sus reportes.
 *
 * Dentro de la organización.
 */
class DeclareImpediments
{
    public const REQUIRED = 'Para ser veedor, declare que no está en ninguno de estos casos.';

    public const BEFORE_REPORTING = 'Antes de reportar, declare que no tiene impedimentos para ser veedor (Ley 850 de 2003, art. 19).';

    /** Only a veedor reports, so only a veedor declares. */
    public static function isAskedOf(User $member): bool
    {
        return $member->hasRole(Roles::Observer->value);
    }

    public static function isPendingFor(User $member): bool
    {
        return self::isAskedOf($member) && $member->impediments_declared_at === null;
    }

    /** The first declaration stands: declaring again does not move its date. */
    public function handle(User $veedor): void
    {
        if ($veedor->impediments_declared_at !== null) {
            return;
        }

        $veedor->forceFill(['impediments_declared_at' => now()])->save();

        AuditLog::record(
            action: 'observer.impediments_declared',
            organizationId: tenant()->getTenantKey(),
            actorType: 'observer',
            actorId: (string) $veedor->id,
            actorName: $veedor->name,
            after: ['user_id' => $veedor->id, 'email' => $veedor->email, 'declared_at' => $veedor->impediments_declared_at->toIso8601String()],
        );
    }
}
