<?php

namespace App\Infrastructure\Tenancy;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Keeps each organization's cache apart. Stancl's bootstrapper does it with
 * tags, which only some stores have (array in the tests, redis): with the
 * database store of development and production, every cache call inside an
 * organization failed, and so did creating one (it. 30, found by make e2e).
 *
 * So: with a store that has tags, Stancl's way, as before; with one that
 * doesn't, a prefix per organization — on the database store, always the
 * central connection (config/cache.php), in the one "cache" table.
 */
class TenantCacheBootstrapper implements TenancyBootstrapper
{
    private bool $taggedByStancl = false;

    private ?string $centralPrefix = null;

    public function __construct(private readonly CacheTenancyBootstrapper $tags) {}

    public function bootstrap(Tenant $tenant)
    {
        if (Cache::store()->getStore() instanceof TaggableStore) {
            $this->taggedByStancl = true;
            $this->tags->bootstrap($tenant);

            return;
        }

        $this->centralPrefix ??= (string) config('cache.prefix');
        $this->usePrefix($this->centralPrefix.'tenant_'.$tenant->getTenantKey().'_');
    }

    public function revert()
    {
        if ($this->taggedByStancl) {
            $this->taggedByStancl = false;
            $this->tags->revert();

            return;
        }

        if ($this->centralPrefix !== null) {
            $this->usePrefix($this->centralPrefix);
            $this->centralPrefix = null;
        }
    }

    /** The stores are built again, with the new prefix, the next time they're used. */
    private function usePrefix(string $prefix): void
    {
        config(['cache.prefix' => $prefix]);
        foreach (array_keys((array) config('cache.stores')) as $store) {
            app('cache')->forgetDriver($store);
        }
        Cache::clearResolvedInstances();
    }
}
