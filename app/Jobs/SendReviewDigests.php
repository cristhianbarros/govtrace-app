<?php

namespace App\Jobs;

use App\Domain\Organization\Notifications\ReviewDigest;
use App\Domain\Organization\OrganizationStatus;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\PendingReview;
use App\Infrastructure\Tenancy\Tenant;
use App\Infrastructure\Tenancy\TenantUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * US-060-MON (it. 43i, V9): every morning, each active Administrador of an
 * active organization with evidences waiting for review gets one email with
 * how many. One a day, not one per evidence: no alert fatigue. A suspended
 * organization gets none — its administrators cannot enter.
 */
class SendReviewDigests implements ShouldQueue
{
    use Dispatchable, Queueable;

    /** @param  string|null  $organizationId  null = every active organization (the nightly run) */
    public function __construct(public readonly ?string $organizationId = null) {}

    public function handle(): void
    {
        $organizations = Tenant::query()
            ->where('status', OrganizationStatus::Active->value)
            ->when($this->organizationId, fn ($query) => $query->whereKey($this->organizationId))
            ->get();

        foreach ($organizations as $tenant) {
            $tenant->run(function () use ($tenant) {
                $pending = PendingReview::count();
                if ($pending === 0) {
                    return;
                }

                $digest = new ReviewDigest($pending, $tenant->displayName(), TenantUrl::to($tenant->domains()->value('domain'), 'admin/inbox'));
                OrganizationUser::role(Roles::Administrator->value, 'tenant')
                    ->where('is_active', true)
                    ->whereNull('invitation_token_hash') // quien no activó su cuenta todavía no entra
                    ->get()
                    ->each->notify($digest);
            });
        }
    }
}
