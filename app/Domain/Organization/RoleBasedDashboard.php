<?php

namespace App\Domain\Organization;

/**
 * Where a tenant user lands after authenticating, by role — shared by the
 * login form (US-031) and accepting an invitation (US-030), since both
 * end the same way: a session for that user, redirected to their panel.
 * Placeholder routes until it. 18/19 build the real ones.
 */
class RoleBasedDashboard
{
    public static function routeFor(User $user): string
    {
        return $user->hasRole(Roles::Administrator->value)
            ? route('organization.dashboard')
            // Hasta "Mis Reportes" (it. 28), el veedor entra a "Nuevo Reporte", su pantalla central.
            : route('reports.new');
    }
}
