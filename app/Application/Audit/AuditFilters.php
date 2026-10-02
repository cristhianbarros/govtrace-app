<?php

namespace App\Application\Audit;

use App\Domain\Audit\AuditLabels;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * It. 46d (US-043-MON): the filters of the audit log, all optional and
 * combinable. They travel as query parameters. Only the Super Administrador
 * may filter by organization; for an Administrador that filter is ignored,
 * so asking for another organization shows nothing of it.
 */
final class AuditFilters
{
    /** @return array{from?: string, to?: string, actor?: string, group?: string, organization?: string} */
    public static function validated(Request $request, bool $global): array
    {
        $filters = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'actor' => ['sometimes', 'nullable', 'string', 'max:100'],
            'group' => ['sometimes', 'nullable', Rule::in(AuditLabels::groupKeys())],
            'organization' => $global ? ['sometimes', 'nullable', 'string', Rule::exists(Tenant::class, 'id')] : ['sometimes'],
        ], [
            'from.date_format' => 'La fecha «desde» no es válida.',
            'to.date_format' => 'La fecha «hasta» no es válida.',
            'to.after_or_equal' => 'La fecha «hasta» no puede ser anterior a «desde».',
            'group.in' => 'Elija un tipo de acción de la lista.',
            'organization.exists' => 'Esa organización no existe.',
        ]);

        return array_filter($filters, fn (mixed $value, string $key) => $value !== null && $value !== '' && ($global || $key !== 'organization'), ARRAY_FILTER_USE_BOTH);
    }
}
