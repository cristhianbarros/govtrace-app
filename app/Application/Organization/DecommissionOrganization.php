<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\OrganizationStatus;
use App\Domain\Organization\SuperAdminAuthorization;
use App\Domain\Organization\User;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Infrastructure\Tenancy\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * US-003b: the Super Administrador decommissions an organization, for
 * good, confirming twice. The first confirmation (start) says what it
 * implies and hands a token valid for 10 minutes; the second (confirm)
 * takes it and the subdomain, typed.
 *
 * It's logical: nothing is deleted and nothing on Stellar changes. Every
 * access is revoked — its users are deactivated, pending invitations and
 * an authorization of the Super Administrador voided, and nobody gets in
 * again (AuthenticateUser, EnsureAccountIsUsable). Its map goes offline
 * (EnsureMapIsOnline); its evidence stays verifiable. Its files are
 * deleted 5 years later (PurgeDecommissionedEvidence).
 */
class DecommissionOrganization
{
    public const CONFIRMATION_MINUTES = 10;

    /**
     * @return array{token: string, summary: array{organization: string, subdomain: string, sealed_reports: int, files_kept_until: string}}
     */
    public function start(Tenant $tenant): array
    {
        $this->ensureCanBeDecommissioned($tenant);

        $token = Str::random(40);
        Cache::put($this->cacheKey($tenant, $token), true, now()->addMinutes(self::CONFIRMATION_MINUTES));

        return [
            'token' => $token,
            'summary' => [
                'organization' => $tenant->displayName(),
                'subdomain' => $tenant->subdomain(),
                'sealed_reports' => $tenant->run(fn () => ReportSeal::query()->where('status', SealStatus::Sealed)->count()),
                'files_kept_until' => $this->filesKeptUntil(CarbonImmutable::now()),
            ],
        ];
    }

    public function confirm(Tenant $tenant, ?string $token, string $typedSubdomain): void
    {
        $this->ensureCanBeDecommissioned($tenant);

        if ($token === null || ! Cache::has($this->cacheKey($tenant, $token))) {
            throw OrganizationValidationException::decommissionNotConfirmed();
        }

        if (Str::lower(trim($typedSubdomain)) !== $tenant->subdomain()) {
            throw OrganizationValidationException::decommissionSubdomainMismatch($tenant->subdomain());
        }

        Cache::forget($this->cacheKey($tenant, $token));

        $previous = $tenant->freshStatus();
        $now = CarbonImmutable::now();

        $tenant->run(function () use ($now) {
            User::query()->update([
                'is_active' => false,
                'invitation_token_hash' => null,
                'invitation_expires_at' => null,
                'remember_token' => null,
            ]);
            SuperAdminAuthorization::query()->whereNull('revoked_at')->update(['revoked_at' => $now]);
        });

        $tenant->update(['status' => OrganizationStatus::Decommissioned->value, 'decommissioned_at' => $now]);

        $actor = Auth::guard('web')->user();

        AuditLog::record(
            action: 'organization.decommissioned',
            organizationId: $tenant->id,
            actorType: 'super_admin',
            actorId: $actor ? (string) $actor->getKey() : null,
            actorName: $actor?->name,
            before: ['status' => $previous->value],
            after: ['status' => OrganizationStatus::Decommissioned->value, 'files_kept_until' => $this->filesKeptUntil($now)],
        );
    }

    private function ensureCanBeDecommissioned(Tenant $tenant): void
    {
        if (tenant()) {
            throw OrganizationValidationException::cannotChangeStatusFromTenantContext();
        }

        if ($tenant->freshStatus() === OrganizationStatus::Decommissioned) {
            throw OrganizationValidationException::alreadyDecommissioned();
        }
    }

    /** The date, in Colombia, until which its files are kept. */
    private function filesKeptUntil(CarbonImmutable $decommissionedAt): string
    {
        return $decommissionedAt->addYears(Tenant::EVIDENCE_RETENTION_YEARS)->timezone('America/Bogota')->toDateString();
    }

    private function cacheKey(Tenant $tenant, string $token): string
    {
        return "organization-decommission:{$tenant->id}:".hash('sha256', $token);
    }
}
