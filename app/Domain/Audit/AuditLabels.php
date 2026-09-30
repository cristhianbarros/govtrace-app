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
        'organization.registered' => 'Dio de alta la organización',
        'organization.administrator_assigned' => 'Asignó el Administrador inicial',
        'organization.legal_data_updated' => 'Cambió los datos legales (NIT o inscripción)',
        'organization.territory_configured' => 'Configuró el territorio',
        'organization.suspended' => 'Suspendió la organización',
        'organization.reactivated' => 'Reactivó la organización',
        'organization.decommissioned' => 'Dio de baja la organización',
        'organization.evidence_files_purged' => 'Borró los archivos de evidencia (5 años después de la baja)',
        'organization.pseudonyms_purged' => 'Borró seudónimos de veedores sin reportes en 5 años',
        'organization.profile_updated' => 'Cambió el nombre o el logo',
        'worksite.location_corrected' => 'Corrigió la ubicación de una obra',
        'worksite.contracts_grouped' => 'Agrupó contratos en una ficha de obra',
        'dossier.downloaded' => 'Descargó el expediente de una obra',
        'evidence.published' => 'Publicó una evidencia',
        'evidence.rejected' => 'Rechazó una evidencia',
        'evidence.withdrawn' => 'Retiró una evidencia',
        'observer.invited' => 'Invitó a un veedor',
        'observer.deactivated' => 'Desactivó a un veedor',
        'observer.reactivated' => 'Reactivó a un veedor',
        'observer.impediments_declared' => 'Declaró no tener impedimentos para ser veedor',
        'privacy.data_authorized' => 'Autorizó el tratamiento de sus datos personales',
        'invitation.resent' => 'Reenvió una invitación',
        'invitation.revoked' => 'Revocó una invitación',
        'parameter.changed' => 'Cambió un parámetro global',
        'seal.resent' => 'Reenvió el sellado de una evidencia',
        'seal.requeued' => 'Volvió a encolar una evidencia en Falla de Sellado',
        'super_admin.authorized' => 'Autorizó al Super Administrador a reportar',
        'super_admin.authorization_revoked' => 'Revocó la autorización al Super Administrador',
        'report.created_by_super_admin' => 'Creó un reporte en nombre de la organización',
    ];

    private const ACTORS = [
        'super_admin' => 'Super Administrador',
        'organization_admin' => 'Administrador de Organización',
        'observer' => 'Veedor de Campo',
        'system' => 'Sistema',
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
