<?php

namespace App\Application\Organization;

use App\Domain\Audit\AuditLog;
use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\OrganizationTerritory;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\SyncSecopContracts;
use Illuminate\Support\Facades\DB;

/**
 * US-012: replaces the organization's whole territory with the given
 * DIVIPOLA codes (2-digit department, 5-digit municipality). Removing a
 * code here only stops future SECOP sync and hides it from the map
 * (it. 7/24) — it never touches evidence or contracts already recorded
 * (the edge scenario in features/US-012.feature).
 */
class ConfigureTerritory
{
    /**
     * @param  list<string>  $codes
     */
    public function handle(Tenant $tenant, array $codes): void
    {
        if ($codes === []) {
            throw OrganizationValidationException::emptyTerritory();
        }

        $picks = array_map(fn (string $code) => $this->resolve($code), $codes);

        $previous = OrganizationTerritory::query()->where('tenant_id', $tenant->id)->get(['department_code', 'municipality_code'])->toArray();

        DB::transaction(function () use ($tenant, $picks) {
            OrganizationTerritory::query()->where('tenant_id', $tenant->id)->delete();

            foreach ($picks as $pick) {
                OrganizationTerritory::create([
                    'tenant_id' => $tenant->id,
                    'department_code' => $pick['department_code'],
                    'municipality_code' => $pick['municipality_code'],
                ]);
            }
        });

        AuditLog::record(
            action: 'organization.territory_configured',
            organizationId: $tenant->id,
            before: ['territory' => $previous],
            after: ['territory' => $picks],
        );

        // US-013, edge "sincronización inmediata al cambiar el territorio".
        SyncSecopContracts::dispatch($tenant->id);
    }

    /**
     * @return array{department_code: ?string, municipality_code: ?string}
     */
    private function resolve(string $code): array
    {
        if (strlen($code) === 2 && Department::query()->whereKey($code)->exists()) {
            return ['department_code' => $code, 'municipality_code' => null];
        }

        if (strlen($code) === 5 && Municipality::query()->whereKey($code)->exists()) {
            return ['department_code' => null, 'municipality_code' => $code];
        }

        throw OrganizationValidationException::unknownGeographyCode();
    }
}
