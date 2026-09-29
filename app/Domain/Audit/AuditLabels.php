<?php

namespace App\Domain\Audit;

/**
 * How the log reads on screen (US-043-MON): each recorded action and each
 * kind of actor, in words. An action without a label shows its code — the
 * entry is never hidden for lack of a translation.
 */
final class AuditLabels
{
    private const ACTIONS = [
        'organization.legal_data_updated' => 'Cambió el NIT',
        'organization.territory_configured' => 'Configuró el territorio',
        'organization.suspended' => 'Suspendió la organización',
        'organization.reactivated' => 'Reactivó la organización',
        'organization.profile_updated' => 'Cambió el nombre o el logo',
        'worksite.location_corrected' => 'Corrigió la ubicación de una obra',
        'worksite.contracts_grouped' => 'Agrupó contratos en una ficha de obra',
        'evidence.published' => 'Publicó una evidencia',
        'evidence.rejected' => 'Rechazó una evidencia',
        'evidence.withdrawn' => 'Retiró una evidencia',
        'observer.deactivated' => 'Desactivó a un veedor',
        'observer.reactivated' => 'Reactivó a un veedor',
        'parameter.changed' => 'Cambió un parámetro global',
        'seal.resent' => 'Reenvió el sellado de una evidencia',
    ];

    private const ACTORS = [
        'super_admin' => 'Super Administrador',
        'organization_admin' => 'Administrador de Organización',
    ];

    public static function action(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    /** "Ana Pérez · Administrador de Organización"; "Sistema" when nobody did it by hand. */
    public static function actor(?string $type, ?string $name): string
    {
        if ($type === null) {
            return 'Sistema';
        }

        return trim(($name ?? '').' · '.(self::ACTORS[$type] ?? $type), ' ·');
    }
}
