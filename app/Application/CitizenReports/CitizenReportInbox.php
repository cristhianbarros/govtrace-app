<?php

namespace App\Application\CitizenReports;

use App\Application\Pagination\Page;
use App\Application\Publication\PublicCache;
use App\Application\Worksites\CorrectWorksiteLocation;
use App\Domain\Audit\AuditLog;
use App\Domain\CitizenReports\CitizenReport;
use App\Domain\CitizenReports\Notifications\CitizenReportAnswered;
use App\Domain\Organization\User;
use App\Domain\Worksites\Worksite;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * US-059-LEG: the reports citizens send, as the Administrador sees them —
 * without the email of whoever sent them (R-LEG-10) — to answer or discard.
 * The answer reaches the citizen through GovTrace. Both go to the audit log.
 *
 * Inside the organization.
 */
class CitizenReportInbox
{
    /**
     * It. 52: a page, the newest first; by status if asked. It. 65a (US-068), like the Bandeja:
     * how many there are in each tab (whatever the filters), and the worksites of the tab to filter by.
     *
     * @return array{data: list<array<string, mixed>>, total: int, has_more: bool, counts: array<string, int>, filters: array{worksites: list<array{id: string, name: string}>}}
     */
    public function list(int $page = 1, ?string $status = null, ?string $worksite = null): array
    {
        $inTab = fn () => CitizenReport::query()->when($status !== null, fn ($query) => $query->where('status', $status));
        $query = $inTab()->with('worksite')
            ->when($worksite !== null, fn ($query) => $query->whereHas('worksite', fn ($sheet) => $sheet->where('public_id', $worksite)))
            ->latest('id');

        return [
            ...Page::of($query, $page, fn ($reports) => $this->presented($reports)),
            'counts' => $this->counts(),
            'filters' => ['worksites' => $this->worksitesIn($inTab())],
        ];
    }

    /** It. 65a: the "Nuevo" ones, for the red number of "Informes ciudadanos" and the daily digest. */
    public static function waiting(): int
    {
        return CitizenReport::query()->where('status', 'new')->count();
    }

    /** @return array<string, int> how many in each state, and all of them */
    private function counts(): array
    {
        $byStatus = CitizenReport::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = array_map(fn (string $status) => (int) ($byStatus[$status] ?? 0), array_combine(array_keys(CitizenReport::STATUS_LABELS), array_keys(CitizenReport::STATUS_LABELS)));

        return [...$counts, 'all' => array_sum($counts)];
    }

    /**
     * @param  Builder<CitizenReport>  $inTab
     * @return list<array{id: string, name: string}>
     */
    private function worksitesIn(Builder $inTab): array
    {
        return Worksite::query()->whereIn('id', $inTab->select('worksite_id'))->get()
            ->map(fn (Worksite $worksite) => ['id' => $worksite->public_id, 'name' => CitizenReportDesk::nameOf($worksite)])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /**
     * @param  Collection<int, CitizenReport>  $reports
     * @return Collection<int, array<string, mixed>>
     */
    private function presented(Collection $reports): Collection
    {
        // It. 58: las propuestas que siguen abiertas para las obras de esta página, para mostrarlas juntas.
        $open = CitizenReport::query()
            ->whereIn('worksite_id', $reports->filter(fn (CitizenReport $report) => $report->proposedLocation() !== null)->pluck('worksite_id'))
            ->whereNotNull('proposed_latitude')
            ->where('status', '!=', 'discarded')
            ->get(['id', 'worksite_id', 'proposed_latitude', 'proposed_longitude'])
            ->groupBy('worksite_id');

        return $reports->map(fn (CitizenReport $report) => [
            // It. 46c (US-064-SEC): identificadores públicos.
            'id' => $report->public_id,
            'reference' => $report->reference(), // la que recibió el ciudadano
            'worksite_id' => $report->worksite?->public_id,
            'worksite' => CitizenReportDesk::nameOf($report->worksite),
            'received_at' => $report->created_at->toIso8601String(),
            'message' => $report->message,
            // It. 46h: de 1 a 3 fotos, por su lugar en la lista (1, 2, 3), no por un número de registro.
            'photo_urls' => array_map(fn (int $index) => "/citizen-reports/{$report->public_id}/photos/".($index + 1), array_keys($report->photo_paths)),
            'status' => $report->status,
            'status_label' => CitizenReport::STATUS_LABELS[$report->status],
            'answer' => $report->answer,
            'proposed_location' => $this->proposal($report, $open->get($report->worksite_id, collect())),
        ]);
    }

    /**
     * It. 58: where the citizen says the work is — unlocated: the work still has no location
     * and it can be confirmed (with the others still open for the same work); confirmed: it
     * was fixed here; elsewhere: the work got its location some other way.
     *
     * @param  Collection<int, CitizenReport>  $open
     * @return array{latitude: float, longitude: float, status: string, others: list<array{latitude: float, longitude: float}>}|null
     */
    private function proposal(CitizenReport $report, Collection $open): ?array
    {
        $point = $report->proposedLocation();
        if ($point === null) {
            return null;
        }

        $status = match (true) {
            $report->location_confirmed_at !== null => 'confirmed',
            $report->worksite?->location() !== null => 'elsewhere',
            default => 'unlocated',
        };

        return [
            'latitude' => $point->latitude,
            'longitude' => $point->longitude,
            'status' => $status,
            'others' => $status !== 'unlocated' ? [] : $open
                ->reject(fn (CitizenReport $other) => $other->id === $report->id)
                ->map(fn (CitizenReport $other) => ['latitude' => $other->proposedLocation()->latitude, 'longitude' => $other->proposedLocation()->longitude])
                ->values()
                ->all(),
        ];
    }

    /**
     * It. 58 (US-035): the Administrador makes the place a citizen proposed the official
     * location of the work — a correction like any other, in the audit log. Only while the
     * work has none: a later one is corrected from "Obras".
     */
    public function confirmLocation(User $administrator, int $id): void
    {
        DB::transaction(function () use ($administrator, $id) {
            $report = CitizenReport::query()->lockForUpdate()->findOrFail($id);
            $point = $report->status === 'discarded' ? null : $report->proposedLocation();
            if ($point === null) {
                throw new DomainException('Este informe no trae una ubicación para confirmar.');
            }

            // Bloqueada como al crear un reporte: un veedor que la fija a la vez espera, y después la encuentra fijada.
            $worksite = Worksite::query()->lockForUpdate()->findOrFail($report->worksite_id);
            if ($worksite->location() !== null) {
                throw new DomainException('La obra ya tiene ubicación. Si está mal, corríjala en Obras.');
            }

            (new CorrectWorksiteLocation)->handle($administrator, $worksite, $point);
            $report->update(['location_confirmed_at' => now()]);
        });

        // Ya confirmada: ninguna visita del mapa guarda lo de antes.
        PublicCache::forget();
    }

    public function answer(User $administrator, int $id, string $answer): void
    {
        $report = $this->pending($id);
        $report->update(['status' => 'answered', 'answer' => trim($answer), 'handled_at' => now(), 'handled_by' => $administrator->id]);

        Notification::route('mail', $report->email)->notify(new CitizenReportAnswered($report->reference(), tenant()->displayName(), $report->answer));
        $this->audit('citizen_report.answered', $administrator, $report);
    }

    public function discard(User $administrator, int $id): void
    {
        $report = $this->pending($id);
        $report->update(['status' => 'discarded', 'handled_at' => now(), 'handled_by' => $administrator->id]);

        $this->audit('citizen_report.discarded', $administrator, $report);
    }

    private function pending(int $id): CitizenReport
    {
        $report = CitizenReport::query()->findOrFail($id);
        if ($report->status !== 'new') {
            throw new DomainException('Este informe ya fue atendido o descartado.');
        }

        return $report;
    }

    /** Never the email: the log says which report and which worksite. */
    private function audit(string $action, User $administrator, CitizenReport $report): void
    {
        AuditLog::record(
            action: $action,
            organizationId: tenant()->getTenantKey(),
            actorType: 'organization_admin',
            actorId: (string) $administrator->id,
            actorName: $administrator->name,
            after: ['citizen_report_id' => $report->id, 'worksite_id' => $report->worksite_id, 'status' => $report->status],
        );
    }
}
