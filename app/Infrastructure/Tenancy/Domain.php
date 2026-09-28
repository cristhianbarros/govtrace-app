<?php

namespace App\Infrastructure\Tenancy;

use RuntimeException;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * No historia in specs/SPEC.md ever lets anyone change an organization's
 * subdomain after US-001 registers it (US-007 only touches name/logo,
 * US-011 only NIT/legal data) — R-SA-03. Enforced here, not with an
 * authorization check, because the capability to change it does not exist
 * for ANY role, Super Administrator included.
 */
class Domain extends BaseDomain
{
    protected static function booted(): void
    {
        static::updating(function (self $domain) {
            if ($domain->isDirty('domain')) {
                throw new RuntimeException(
                    'El subdominio de una organización no se puede modificar después de su alta.'
                );
            }
        });
    }
}
