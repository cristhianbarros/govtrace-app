<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\Nit;
use App\Domain\Organization\Registration;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Facades\Auth;

/**
 * US-011: the Super Administrator only, at a formal request from the
 * organization (a change of legal name or NIT). Same structural guard as
 * RegisterOrganization (R-TA-01/R-TA-03) — nobody inside a tenant's own
 * context can reach this, regardless of role.
 */
class UpdateOrganizationLegalData
{
    /**
     * The NIT, the registration (R-LEG-06, it. 44d), or both: the same rules
     * as the alta, and never neither. What is not given is removed.
     */
    public function handle(Tenant $tenant, ?string $newNit, ?string $registrationNumber = null, ?string $registrationAuthority = null): Tenant
    {
        if (tenant()) {
            throw OrganizationValidationException::cannotEditLegalDataFromTenantContext();
        }

        $nit = filled($newNit) ? Nit::fromString($newNit) : null;
        $registration = Registration::from($registrationNumber, $registrationAuthority);
        if ($nit === null && $registration === null) {
            throw OrganizationValidationException::identificationRequired();
        }

        $others = Tenant::query()->where('id', '!=', $tenant->id);
        if ($nit && (clone $others)->where('nit', $nit->value())->exists()) {
            throw OrganizationValidationException::duplicateNit();
        }
        if ($registration && (clone $others)->where('registration_key', $registration->key())->exists()) {
            throw OrganizationValidationException::duplicateRegistration();
        }

        $before = $this->legalData($tenant->nit, $tenant->registration_number ? "{$tenant->registration_number} · {$tenant->registration_authority}" : null);
        $tenant->update([
            'nit' => $nit?->value(),
            'registration_number' => $registration?->number,
            'registration_authority' => $registration?->authority,
            'registration_key' => $registration?->key(),
        ]);

        $actor = Auth::guard('web')->user();
        AuditLog::record(
            action: 'organization.legal_data_updated',
            organizationId: $tenant->id,
            actorType: 'super_admin',
            actorId: $actor ? (string) $actor->getKey() : null,
            actorName: $actor?->name,
            before: $before,
            after: $this->legalData($nit?->value(), $registration?->describe()),
        );

        return $tenant->refresh();
    }

    /** @return array{nit: ?string, registration?: string} */
    private function legalData(?string $nit, ?string $registration): array
    {
        return ['nit' => $nit, ...($registration ? ['registration' => $registration] : [])];
    }
}
