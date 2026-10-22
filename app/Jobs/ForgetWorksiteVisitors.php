<?php

namespace App\Jobs;

use App\Application\Publication\WorksiteVisits;
use App\Domain\Worksites\VisitorKey;
use App\Domain\Worksites\WorksiteVisitor;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * It. 69 (US-071, R-PRIV-07): every day, once the day is over in Colombia, the
 * fingerprints of whoever saw the page of a work and the keys of the days
 * before go away. The number of people of each day stays.
 */
class ForgetWorksiteVisitors implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        $today = WorksiteVisits::today()->toDateString();

        foreach (Tenant::query()->get() as $tenant) {
            $tenant->run(fn () => WorksiteVisitor::query()->where('day', '<', $today)->delete());
        }
        VisitorKey::query()->where('day', '<', $today)->delete();
    }
}
