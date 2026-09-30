<?php

namespace App\Application\Organization;

use App\Infrastructure\Tenancy\Tenant;

/**
 * US-001 + US-002, in one step from the Super Administrador's panel
 * (it. 19): register the organization and, when its first Administrador
 * comes filled in the same form, assign it right away — without leaving
 * the organization without one until a second visit to the panel.
 */
class RegisterOrganizationWithAdministrator
{
    public function handle(?string $nit, string $name, string $subdomain, ?string $administratorName, ?string $administratorEmail, ?string $registrationNumber = null, ?string $registrationAuthority = null): Tenant
    {
        $tenant = (new RegisterOrganization)->handle($nit, $name, $subdomain, $registrationNumber, $registrationAuthority);

        if ($administratorName !== null && $administratorEmail !== null) {
            try {
                (new AssignInitialAdministrator)->handle($tenant, $administratorName, $administratorEmail);
            } catch (\Throwable $e) {
                // No queda una organización a medio configurar: si el
                // Administrador inicial no es válido, el alta entera se
                // deshace (el subdominio queda libre otra vez).
                $tenant->delete();

                throw $e;
            }
        }

        return $tenant;
    }
}
