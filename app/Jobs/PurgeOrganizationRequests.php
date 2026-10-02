<?php

namespace App\Jobs;

use App\Application\Organization\RegistrationDocuments;
use App\Domain\Organization\OrganizationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * US-062-ALT (it. 43k): a request for an alta, approved or rejected, is
 * deleted 30 days after the decision, with its contact email — like the
 * reports of citizens (it. 45c). A pending one waits for its decision.
 */
class PurgeOrganizationRequests implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const RETENTION_DAYS = 30;

    public function handle(): void
    {
        OrganizationRequest::query()
            ->where('status', '!=', 'pending')
            ->where('decided_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->each(function (OrganizationRequest $request) {
                // It. 46b: con su PDF. El de una aprobada ya pasó a su organización.
                RegistrationDocuments::forget($request->document_path);
                $request->delete();
            });
    }
}
