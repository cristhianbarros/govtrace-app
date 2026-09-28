<?php

namespace App\Application\Organization;

use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\Nit;
use App\Domain\Organization\OrganizationName;
use App\Domain\Organization\Subdomain;
use App\Infrastructure\Tenancy\Tenant;

/**
 * US-001: only the Super Administrator runs this. Registering an
 * organization creates the tenant AND its domain in one step — the alta
 * itself is the approval, there is no separate pending state
 * (project-context.md, decisión confirmada en Épicas P4).
 *
 * Order matters: format/check-digit validation happens before the
 * uniqueness queries, so a malformed NIT never touches the database
 * (matches the Gherkin: duplicate-NIT scenarios always use a
 * well-formed NIT).
 */
class RegisterOrganization
{
    public function handle(string $nit, string $name, string $subdomain): Tenant
    {
        $nit = Nit::fromString($nit);
        $subdomain = Subdomain::fromString($subdomain);
        $name = OrganizationName::fromString($name);

        if (Tenant::query()->where('nit', $nit->value())->exists()) {
            throw OrganizationValidationException::duplicateNit();
        }

        $domainName = "{$subdomain->value}.".config('tenancy.apex_domain');

        if (config('tenancy.domain_model')::query()->where('domain', $domainName)->exists()) {
            throw OrganizationValidationException::duplicateSubdomain();
        }

        $tenant = Tenant::create([
            'nit' => $nit->value(),
            'name' => $name->value,
            'status' => 'active',
        ]);

        $tenant->domains()->create(['domain' => $domainName]);

        return $tenant;
    }
}
