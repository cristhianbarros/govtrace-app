<?php

namespace App\Jobs;

use App\Application\Organization\OrganizationActivity;
use App\Domain\Organization\Notifications\OrganizationInactive;
use App\Domain\Organization\OrganizationStatus;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * US-054-RPT: cada día, las organizaciones activas que llevan 30 días sin
 * recibir ni publicar evidencias — o sin ninguna desde que se registraron —
 * le llegan al Super Administrador por Email. Una alerta por período de
 * inactividad: si vuelve a tener actividad y se detiene, otra.
 */
class CheckOrganizationActivity implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const INACTIVE_AFTER_DAYS = 30;

    public function handle(OrganizationActivity $activity): void
    {
        $now = CarbonImmutable::now();

        foreach (Tenant::query()->where('status', OrganizationStatus::Active->value)->orderBy('name')->get() as $tenant) {
            $last = $activity->lastOf($tenant) ?? CarbonImmutable::parse($tenant->created_at);

            if ($last->greaterThan($now->subDays(self::INACTIVE_AFTER_DAYS))) {
                continue;
            }

            if (Cache::add("organization-inactive:{$tenant->id}:{$last->getTimestamp()}", $now->toIso8601String())) {
                Notification::send(SuperAdmin::all(), new OrganizationInactive($tenant->displayName(), (int) $last->diffInDays($now), $last));
            }
        }
    }
}
