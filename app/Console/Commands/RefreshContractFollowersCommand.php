<?php

namespace App\Console\Commands;

use App\Application\Worksites\ContractFollowers;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Console\Command;

/**
 * It. 59 (US-066): recounts, from each organization's own database, the
 * central summary of who follows which contract. Every night and on every
 * deploy: what a missed update left behind, it puts right.
 */
class RefreshContractFollowersCommand extends Command
{
    protected $signature = 'contracts:followers';

    protected $description = 'Recount which organizations follow which contracts (the summary the Home page suggests from)';

    public function handle(): int
    {
        $organizations = Tenant::query()->get();
        foreach ($organizations as $organization) {
            $organization->run(fn () => ContractFollowers::refreshOrganization());
        }

        $this->info("Resumen recalculado para {$organizations->count()} veedurías.");

        return self::SUCCESS;
    }
}
