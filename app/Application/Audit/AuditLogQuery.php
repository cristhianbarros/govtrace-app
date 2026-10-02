<?php

namespace App\Application\Audit;

use App\Domain\Audit\AuditLabels;
use App\Domain\Audit\AuditLog;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * US-043-MON: the audit log, by scope. The Super Administrador reads all of
 * it; an Administrador de Organización, only the entries of theirs — an
 * entry of another organization is simply not found. Newest first, 20 per
 * page; each entry says who, when, what, and the value before and after.
 * It. 46d: filtered by date (days of Colombia), who did it, action type and,
 * for the Super Administrador, organization — all at once (AuditFilters).
 */
class AuditLogQuery
{
    private const PER_PAGE = 20;

    /** @param  string|null  $organizationId  null: the whole log (Super Administrador) */
    public function __construct(private readonly ?string $organizationId) {}

    /** @param  array{from?: string, to?: string, actor?: string, group?: string, organization?: string}  $filters */
    public function page(array $filters = []): LengthAwarePaginator
    {
        return $this->filtered($filters)->orderByDesc('created_at')->orderByDesc('id')->paginate(self::PER_PAGE);
    }

    /** What the filters can choose from; the organizations only for the Super Administrador. */
    public function options(): array
    {
        return [
            'groups' => AuditLabels::groups(),
            'organizations' => $this->organizationId === null
                ? Tenant::query()->orderBy('name')->get(['id', 'name'])->map(fn (Tenant $tenant) => ['id' => $tenant->id, 'name' => $tenant->name])->all()
                : [],
        ];
    }

    private function filtered(array $filters): Builder
    {
        $zone = 'America/Bogota';

        return $this->scoped()
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from, $zone)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to, $zone)->endOfDay()->utc()))
            ->when($filters['actor'] ?? null, fn (Builder $query, string $actor) => $query->where('actor_name', 'ilike', '%'.addcslashes($actor, '%_\\').'%'))
            ->when($filters['group'] ?? null, fn (Builder $query, string $group) => $query->whereIn('action', AuditLabels::actionsOf($group)))
            ->when($filters['organization'] ?? null, fn (Builder $query, string $organization) => $query->where('organization_id', $organization));
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
            'sentence' => AuditLabels::sentence($entry->actor_type, $entry->actor_name, $entry->action, $entry->before, $entry->after),
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
