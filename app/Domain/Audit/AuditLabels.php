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
        // It. 43j (V3): faltaban.
        'organization.administrator_invited' => 'Invitó a otro administrador',
        'organization.administrator_deactivated' => 'Desactivó a un administrador',
        'organization.administrator_reactivated' => 'Reactivó a un administrador',
        // It. 46a (US-063-USR).
        'super_admin.invited' => 'Invitó a un Super Administrador',
        'super_admin.invitation_resent' => 'Reenvió la invitación de un Super Administrador',
        'super_admin.invitation_revoked' => 'Revocó la invitación de un Super Administrador',
        'super_admin.activated' => 'Activó su cuenta de Super Administrador',
        'super_admin.deactivated' => 'Desactivó a un Super Administrador',
        'super_admin.reactivated' => 'Reactivó a un Super Administrador',
        'organization_request.approved' => 'Aprobó una solicitud de alta',
        'organization_request.rejected' => 'Rechazó una solicitud de alta',
        'organization.legal_data_updated' => 'Cambió los datos legales (NIT o inscripción)',
        'organization.territory_configured' => 'Configuró el territorio',
        'organization.suspended' => 'Suspendió la organización',
        'organization.reactivated' => 'Reactivó la organización',
        'organization.decommissioned' => 'Dio de baja la organización',
        'organization.evidence_files_purged' => 'Borró los archivos de evidencia (5 años después de la baja)',
        'organization.pseudonyms_purged' => 'Borró seudónimos de veedores sin reportes en 5 años',
        'citizen_reports.purged' => 'Borró informes ciudadanos descartados y correos de informes atendidos hace 30 días',
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
        'citizen_report.answered' => 'Respondió un informe ciudadano',
        'citizen_report.discarded' => 'Descartó un informe ciudadano',
        'invitation.resent' => 'Reenvió una invitación',
        'invitation.revoked' => 'Revocó una invitación',
        'parameter.changed' => 'Cambió un parámetro global',
        'seal.resent' => 'Reenvió el sellado de una evidencia',
        'seal.requeued' => 'Volvió a encolar una evidencia en Falla de Sellado',
        'super_admin.authorized' => 'Autorizó al Super Administrador a reportar',
        'super_admin.authorization_revoked' => 'Revocó la autorización al Super Administrador',
        'report.created_by_super_admin' => 'Creó un reporte en nombre de la organización',
        'super_admin.created' => 'Creó un Super Administrador desde la consola del servidor',
    ];

    /** It. 46d: the action types of the filter, in the order they are offered. */
    private const GROUPS = [
        'organizaciones' => 'Organizaciones y obras',
        'evidencias' => 'Evidencias e informes',
        'cuentas' => 'Cuentas e invitaciones',
        'configuracion' => 'Configuración',
        'sellado' => 'Sellado',
    ];

    /** Actions whose prefix would put them in another type. */
    private const GROUP_EXCEPTIONS = [
        'organization.profile_updated' => 'configuracion',
        'organization.territory_configured' => 'configuracion',
        'organization.administrator_assigned' => 'cuentas',
        'organization.administrator_invited' => 'cuentas',
        'organization.administrator_deactivated' => 'cuentas',
        'organization.administrator_reactivated' => 'cuentas',
    ];

    private const GROUP_BY_PREFIX = [
        'organization' => 'organizaciones', 'organization_request' => 'organizaciones', 'worksite' => 'organizaciones', 'dossier' => 'organizaciones',
        'evidence' => 'evidencias', 'report' => 'evidencias', 'citizen_report' => 'evidencias', 'citizen_reports' => 'evidencias',
        'observer' => 'cuentas', 'super_admin' => 'cuentas', 'invitation' => 'cuentas', 'privacy' => 'cuentas',
        'parameter' => 'configuracion',
        'seal' => 'sellado',
    ];

    private const ACTORS = [
        'super_admin' => 'Super Administrador',
        'organization_admin' => 'Administrador de Organización',
        'observer' => 'Veedor de Campo',
        'system' => 'Sistema',
    ];

    /** @return list<array{key: string, label: string}> */
    public static function groups(): array
    {
        return array_map(fn (string $key, string $label) => ['key' => $key, 'label' => $label], array_keys(self::GROUPS), self::GROUPS);
    }

    public static function groupKeys(): array
    {
        return array_keys(self::GROUPS);
    }

    public static function groupOf(string $action): ?string
    {
        return self::GROUP_EXCEPTIONS[$action] ?? self::GROUP_BY_PREFIX[strstr($action, '.', true)] ?? null;
    }

    /** @return list<string> the labeled actions of that type */
    public static function actionsOf(string $group): array
    {
        return array_values(array_filter(self::labeledActions(), fn (string $action) => self::groupOf($action) === $group));
    }

    /** @return list<string> */
    public static function labeledActions(): array
    {
        return array_keys(self::ACTIONS);
    }

    /**
     * "Ana Directora desactivó a un Super Administrador (luis@govtrace.org)":
     * who, the action as a verb, and the email of who it was done to, if recorded.
     */
    public static function sentence(?string $type, ?string $name, string $action, ?array $before, ?array $after): string
    {
        $who = $type === null || $type === 'system' ? 'El sistema' : ($name !== null && $name !== '' ? $name : (self::ACTORS[$type] ?? $type));
        $label = self::action($action);
        $email = $after['email'] ?? $before['email'] ?? null;

        return $who.' '.mb_strtolower(mb_substr($label, 0, 1)).mb_substr($label, 1).(is_string($email) ? " ({$email})" : '');
    }

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
