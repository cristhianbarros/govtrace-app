<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\Tenant;
use Carbon\CarbonImmutable;

/**
 * US-054-RPT: la actividad de una organización es recibir o publicar
 * evidencias — iniciar sesión, no. Lo recibido está en su base; lo
 * publicado, en el log de auditoría (una evidencia publicada y luego
 * retirada también fue publicada).
 */
final class OrganizationActivity
{
    /** The last time $tenant received or published an evidence; null if it never did. */
    public function lastOf(Tenant $tenant): ?CarbonImmutable
    {
        $moments = array_filter([
            $tenant->run(fn () => Report::query()->max('received_at')),
            AuditLog::query()->where('organization_id', $tenant->id)->where('action', 'evidence.published')->max('created_at'),
        ]);

        return $moments === [] ? null : CarbonImmutable::parse(max($moments));
    }
}
