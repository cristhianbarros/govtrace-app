<?php

namespace App\Application\Demo;

use App\Infrastructure\Tenancy\Tenant;

/** What make demo leaves ready, and how to enter it. */
final class DemoEnvironment
{
    /**
     * @param  list<array{role: string, url: string, email: string, password: string}>  $credentials
     * @param  int  $published  the reports the demo published
     * @param  int  $pendingSeals  the reports that did not reach "Sellada" while waiting
     */
    public function __construct(
        public readonly Tenant $organization,
        public readonly string $url,
        public readonly array $credentials,
        public readonly int $published,
        public readonly int $pendingSeals,
    ) {}
}
