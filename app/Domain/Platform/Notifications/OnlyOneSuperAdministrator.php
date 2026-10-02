<?php

namespace App\Domain\Platform\Notifications;

use App\Domain\Sealing\Notifications\CriticalAlert;

/**
 * It. 46a (US-063-USR): the platform was left with a single active Super
 * Administrador. If that person loses the access, nobody can approve
 * veedurías, change their administrators or attend the alerts.
 */
class OnlyOneSuperAdministrator extends CriticalAlert
{
    public function subject(): string
    {
        return 'GovTrace: queda un solo Super Administrador activo';
    }

    public function message(): string
    {
        return '⚠️ Aviso: Solo hay un Super Administrador activo. Si pierde el acceso, nadie podrá dar de alta veedurías ni atender las alertas. Invite a otro. Se hace en "Super Administradores", en el panel global.';
    }
}
