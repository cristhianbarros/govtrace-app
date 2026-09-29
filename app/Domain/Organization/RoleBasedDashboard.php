<?php

namespace App\Domain\Organization;

/**
 * Where a tenant user lands after authenticating, by role — shared by the
 * login form (US-031) and accepting an invitation (US-030), since both
 * end the same way: a session for that user, redirected to their panel.
 */
class RoleBasedDashboard
{
    public static function routeFor(User $user): string
    {
        return $user->hasRole(Roles::Administrator->value)
            ? route('organization.dashboard')
            // El veedor entra a "Nuevo Reporte", su pantalla central; desde ahí llega a "Mis Reportes".
            : route('reports.new');
    }
}
