<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\Nit;
use App\Domain\Organization\OrganizationName;
use App\Domain\Organization\Subdomain;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\SyncSecopContracts;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * US-001: only the Super Administrator runs this. Registering an
 * organization creates the tenant AND its domain in one step — the alta
 * itself is the approval, there is no separate pending state
 * (project-context.md, decisión confirmada en Épicas P4). All or nothing:
 * a failure half way leaves no organization behind (it. 36), and the alta
 * goes to the audit log (R-AUD-04).
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
        // Structural guard for R-TA-01: the Super Administrator operates
        // from the central domain, where tenancy is never initialized.
        // Reaching this method WHILE a tenant is active means the caller
        // is inside an organization's own context (an Administrador de
        // Organización can never be central) — reject regardless of who
        // it is.
        if (tenant()) {
            throw OrganizationValidationException::cannotRegisterFromTenantContext();
        }

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

        $tenant = new Tenant([
            'nit' => $nit->value(),
            'name' => $name->value,
            'status' => 'active',
        ]);

        try {
            // Guardarla crea y migra su base (TenantCreated); después, su dominio.
            $tenant->save();
            $tenant->domains()->create(['domain' => $domainName]);
        } catch (Throwable $e) {
            // Nada a medio crear: el NIT y el subdominio quedan libres otra vez.
            $this->undo($tenant);

            throw $e;
        }

        $actor = Auth::guard('web')->user();

        AuditLog::record(
            action: 'organization.registered',
            organizationId: $tenant->id,
            actorType: 'super_admin',
            actorId: $actor ? (string) $actor->getKey() : null,
            actorName: $actor?->name,
            after: ['nit' => $nit->value(), 'name' => $name->value, 'subdomain' => $domainName],
        );

        // US-013, edge "sincronización inmediata al dar de alta": no
        // espera a la corrida nocturna. Recién creada todavía no tiene
        // territorio (US-012 es un paso aparte), así que esta primera
        // corrida no consulta nada; la que trae contratos es la que
        // despacha ConfigureTerritory.
        SyncSecopContracts::dispatch($tenant->id);

        return $tenant;
    }

    /**
     * What got created before the failure: its row and, if it got that far,
     * its database (TenantDeleted drops it). The row goes first; if the
     * database never existed, dropping it fails, and that is fine.
     */
    private function undo(Tenant $tenant): void
    {
        if ($tenant->exists) {
            rescue(fn () => $tenant->delete(), report: false);
        }
    }
}
