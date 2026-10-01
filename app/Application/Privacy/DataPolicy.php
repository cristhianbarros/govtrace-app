<?php

namespace App\Application\Privacy;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;

/**
 * US-058-LEG (it. 44e): la política de tratamiento de datos personales (Ley
 * 1581 de 2012) y la autorización de cada persona al crear su cuenta —
 * previa, expresa e informada (art. 9) —, guardada con su fecha y la versión
 * de la política que aceptó: esa es su prueba.
 *
 * El texto vive en la pantalla (Public/Privacy.vue); aquí, su versión y los
 * datos del responsable, que vienen de la configuración (R-LEG-08).
 */
class DataPolicy
{
    /** The text in force. Changing it means a new version: it. 44f added the reports of citizens, 45c their retention, 43k the requests for an alta. */
    public const VERSION = '2026-10-01.1';

    public const EFFECTIVE_DATE = '2026-10-01';

    public const AUTHORIZATION_REQUIRED = 'Para crear su cuenta, autorice el tratamiento de sus datos personales.';

    private const CONTROLLER_FIELDS = [
        'name' => 'el nombre o la razón social',
        'identification' => 'la identificación (NIT)',
        'address' => 'el domicilio y la dirección',
        'email' => 'el correo electrónico',
        'phone' => 'el teléfono',
    ];

    /** @return array<string, mixed> what the policy screen shows */
    public function props(): array
    {
        $controller = array_merge(array_fill_keys(array_keys(self::CONTROLLER_FIELDS), null), (array) config('privacy.controller'));

        return [
            'version' => self::VERSION,
            'effective_date' => implode('/', array_reverse(explode('-', self::EFFECTIVE_DATE))),
            'controller' => $controller,
            // Lo que falta para que deje de ser un borrador.
            'missing' => array_values(array_map(
                fn (string $field) => self::CONTROLLER_FIELDS[$field],
                array_keys(array_filter(self::CONTROLLER_FIELDS, fn (string $label, string $field) => blank($controller[$field]), ARRAY_FILTER_USE_BOTH)),
            )),
            'hosting' => config('privacy.hosting'),
        ];
    }

    /** Inside the organization: the member authorizes, when activating their account. */
    public function authorize(User $member): void
    {
        $member->forceFill(['data_authorized_at' => now(), 'data_policy_version' => self::VERSION])->save();

        AuditLog::record(
            action: 'privacy.data_authorized',
            organizationId: tenant()->getTenantKey(),
            actorType: $member->hasRole(Roles::Observer->value) ? 'observer' : 'organization_admin',
            actorId: (string) $member->id,
            actorName: $member->name,
            after: ['user_id' => $member->id, 'email' => $member->email, 'policy_version' => self::VERSION],
        );
    }
}
