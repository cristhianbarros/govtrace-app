<?php

namespace App\Application\Audit;

use App\Domain\Audit\AuditLabels;
use App\Domain\Audit\AuditLog;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * US-043-MON: the audit log, by scope. The Super Administrador reads all of
 * it; an Administrador de Organización, only the entries of theirs — an
 * entry of another organization is simply not found. Newest first, 20 per
 * page; each entry says who, when, what, and the value before and after.
 */
class AuditLogQuery
{
    private const PER_PAGE = 20;

    /** @param  string|null  $organizationId  null: the whole log (Super Administrador) */
    public function __construct(private readonly ?string $organizationId) {}

    public function page(): LengthAwarePaginator
    {
        return $this->scoped()->orderByDesc('created_at')->orderByDesc('id')->paginate(self::PER_PAGE);
    }

    public function find(int $id): AuditLog
    {
        return $this->scoped()->findOrFail($id);
    }

    /** @return array<string, mixed> */
    public static function present(AuditLog $entry, ?string $organizationName): array
    {
        return [
            'id' => $entry->id,
            'created_at' => $entry->created_at->toIso8601String(),
            'organization' => $organizationName,
            'actor' => AuditLabels::actor($entry->actor_type, $entry->actor_name),
            'action' => AuditLabels::action($entry->action),
            'before' => $entry->before,
            'after' => $entry->after,
        ];
    }

    /**
     * @param  iterable<AuditLog>  $entries
     * @return list<array<string, mixed>>
     */
    public static function presentAll(iterable $entries): array
    {
        $entries = collect($entries);
        $names = Tenant::query()->whereIn('id', $entries->pluck('organization_id')->filter()->unique())->pluck('name', 'id');

        return $entries->map(fn (AuditLog $entry) => self::present($entry, $names[$entry->organization_id] ?? null))->values()->all();
    }

    private function scoped(): Builder
    {
        return AuditLog::query()->when($this->organizationId !== null, fn (Builder $query) => $query->where('organization_id', $this->organizationId));
    }
}
