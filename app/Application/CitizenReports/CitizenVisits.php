<?php

namespace App\Application\CitizenReports;

use App\Application\Publication\PublicCache;
use App\Domain\Audit\AuditLog;
use App\Domain\CitizenReports\CitizenReport;
use App\Domain\CitizenReports\Notifications\CitizenReportDocumented;
use App\Domain\CitizenReports\Notifications\CitizenVisitAssigned;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Domain\Reports\Report;
use App\Infrastructure\Tenancy\TenantUrl;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * It. 65b (US-068, R-LEG-11): a citizen report becomes a sealed evidence through
 * the report of a veedor. The Administrador asks a veedor for the visit (the
 * last one who reported the work comes suggested); the veedor sees it, with
 * the message and the photos of the citizen, never their email; the next
 * report of that veedor on that work documents it. Published, the citizen
 * report is "Documentado". The citizen hears when a veedor is asked, once, and
 * when the evidence is published.
 */
class CitizenVisits
{
    /** @return Collection<int, User> the veedores who may get a visit: active, with their account and their declaration */
    public static function observers(): Collection
    {
        return self::eligible()->orderBy('name')->get(['id', 'public_id', 'name']);
    }

    /**
     * The veedor who last reported each worksite, if they may still get a visit.
     *
     * @param  list<int>  $worksiteIds
     * @return array<int, string> worksite id => public id of the veedor
     */
    public static function suggestions(array $worksiteIds): array
    {
        $eligible = self::eligible()->pluck('public_id', 'id');

        return Report::query()->whereIn('worksite_id', $worksiteIds)->orderBy('captured_at')->orderBy('id')->get(['worksite_id', 'user_id'])
            ->keyBy('worksite_id')
            ->map(fn (Report $report) => $eligible[$report->user_id] ?? null)
            ->filter()
            ->all();
    }

    /** @return string what the screen says */
    public function assign(User $administrator, int $id, ?string $observerId): string
    {
        $observer = $observerId === null ? null : self::eligible()->where('public_id', $observerId)->first();
        if ($observerId === null) {
            throw ValidationException::withMessages(['observer' => 'Elija al veedor que va a visitar la obra.']);
        }
        if ($observer === null) {
            throw ValidationException::withMessages(['observer' => 'Ese veedor no puede recibir la visita: debe estar activo y haber declarado sus impedimentos.']);
        }

        $report = DB::transaction(function () use ($administrator, $id, $observer) {
            $report = CitizenReport::query()->lockForUpdate()->findOrFail($id);
            if (! in_array($report->status, ['new', 'assigned'], true)) {
                throw new DomainException('Este informe ya fue respondido, documentado o descartado.');
            }
            $before = ['status' => $report->status, 'observer_id' => $report->assigned_to];
            // It. 66a: pedir la visita es una primera acción: detiene el plazo, aunque la visita vuelva a "Nuevo".
            $report->update(['status' => 'assigned', 'assigned_to' => $observer->id, 'assigned_at' => now(), 'visit_note' => null, 'documenting_report_id' => null, 'first_action_at' => $report->first_action_at ?? now()]);
            $this->audit('citizen_report.visit_assigned', 'organization_admin', $administrator, $report, $before);

            return $report;
        });

        // It. 66b: lo público dice qué está haciendo la veeduría.
        PublicCache::forget();

        // Al ciudadano, una vez: si la visita cambia de veedor, no le llega otro correo.
        if ($report->citizen_notified_at !== null) {
            return "Le pedimos la visita a {$observer->name}.";
        }
        // It. 66b: a quien informó y a quienes se sumaron, a cada uno en su correo.
        foreach ($report->recipients() as $email) {
            Notification::route('mail', $email)->notify(new CitizenVisitAssigned($report->reference(), tenant()->displayName(), CitizenReportDesk::nameOf($report->worksite)));
        }
        $report->update(['citizen_notified_at' => now()]);

        return "Le pedimos la visita a {$observer->name}. Al ciudadano le avisamos que un veedor va a visitar la obra.";
    }

    /** @return list<array<string, mixed>> the visits $veedor has not documented yet, oldest first */
    public static function pendingFor(User $veedor): array
    {
        return self::pending()->where('assigned_to', $veedor->id)->with('worksite.contracts')->orderBy('assigned_at')->get()
            ->map(fn (CitizenReport $report) => self::presented($report))
            ->all();
    }

    /** One of the pending visits of $veedor, or a 404: a veedor only sees their own. */
    public static function ofVeedor(User $veedor, string $publicId): CitizenReport
    {
        return self::pending()->where('assigned_to', $veedor->id)->where('public_id', $publicId)->firstOrFail();
    }

    /** "No puedo ir": back to the Administrador, as "Nuevo", saying who could not go. */
    public function unable(User $veedor, string $publicId): void
    {
        DB::transaction(function () use ($veedor, $publicId) {
            $report = self::ofVeedor($veedor, $publicId);
            $before = ['status' => $report->status, 'observer_id' => $report->assigned_to];
            $report->update(['status' => 'new', 'assigned_to' => null, 'assigned_at' => null, 'visit_note' => "{$veedor->name} no pudo ir."]);
            $this->audit('citizen_report.visit_unable', 'observer', $veedor, $report, $before);
        });
        PublicCache::forget();
    }

    /** The report of the assigned veedor on the work documents the visits it had pending there. */
    public static function documentedBy(Report $report): void
    {
        self::pending()->where('worksite_id', $report->worksite_id)->where('assigned_to', $report->user_id)
            ->update(['documenting_report_id' => $report->id]);
    }

    /** Its evidence published: "Documentado", and the link for the citizen. */
    public function published(User $administrator, Report $report): void
    {
        $documented = CitizenReport::query()->where('documenting_report_id', $report->id)->where('status', 'assigned')->with('worksite')->get();
        foreach ($documented as $citizenReport) {
            $citizenReport->update(['status' => 'documented', 'documented_at' => now(), 'handled_at' => now(), 'handled_by' => $administrator->id]);
            $this->audit('citizen_report.documented', 'organization_admin', $administrator, $citizenReport, ['status' => 'assigned']);

            $worksite = $citizenReport->worksite;
            $url = TenantUrl::to(tenant()->domains()->value('domain'), "worksite/{$worksite->public_id}");
            foreach ($citizenReport->recipients() as $email) {
                Notification::route('mail', $email)->notify(new CitizenReportDocumented($citizenReport->reference(), tenant()->displayName(), CitizenReportDesk::nameOf($worksite), $url));
            }
        }
    }

    /** Its evidence rejected: the visit is pending again, for the same veedor. */
    public static function rejected(Report $report): void
    {
        CitizenReport::query()->where('documenting_report_id', $report->id)->where('status', 'assigned')->update(['documenting_report_id' => null]);
    }

    /** A deactivated veedor can't go: their visits go back to the Administrador. */
    public static function observerDeactivated(User $observer): void
    {
        CitizenReport::query()->where('assigned_to', $observer->id)->where('status', 'assigned')
            ->update(['status' => 'new', 'assigned_to' => null, 'assigned_at' => null, 'documenting_report_id' => null, 'visit_note' => 'El veedor ya no está activo.']);
        PublicCache::forget();
    }

    /** @return Builder<CitizenReport> */
    private static function pending(): Builder
    {
        return CitizenReport::query()->where('status', 'assigned')->whereNull('documenting_report_id');
    }

    /** @return Builder<User> */
    private static function eligible(): Builder
    {
        return User::role(Roles::Observer->value, 'tenant')
            ->where('is_active', true)
            ->whereNull('invitation_token_hash')
            ->whereNotNull('impediments_declared_at');
    }

    /** @return array<string, mixed> what the veedor sees: never the email of the citizen */
    private static function presented(CitizenReport $report): array
    {
        $worksite = $report->worksite;
        $contract = Contract::query()->whereIn('secop_contract_id', $worksite->contracts->pluck('secop_contract_id'))->orderBy('secop_contract_id')
            ->first(['secop_contract_id', 'object', 'entity_name', 'municipality_code', 'department_code']);
        $proposed = $worksite->latitude === null ? $report->proposedLocation() : null;

        return [
            'id' => $report->public_id,
            'reference' => $report->reference(),
            'worksite' => CitizenReportDesk::nameOf($worksite),
            'message' => $report->message,
            'photo_urls' => array_map(fn (int $index) => "/visits/{$report->public_id}/photos/".($index + 1), array_keys(array_values($report->photo_paths ?? []))),
            'contract' => $contract?->only(['secop_contract_id', 'object', 'entity_name']),
            'proposed_location' => $proposed === null ? null : ['latitude' => $proposed->latitude, 'longitude' => $proposed->longitude],
            'assigned_at' => $report->assigned_at?->toIso8601String(),
        ];
    }

    /** @param  array<string, mixed>  $before */
    private function audit(string $action, string $actorType, User $actor, CitizenReport $report, array $before): void
    {
        AuditLog::record(
            action: $action,
            organizationId: tenant()->getTenantKey(),
            actorType: $actorType,
            actorId: (string) $actor->id,
            actorName: $actor->name,
            before: ['citizen_report_id' => $report->id, ...$before],
            after: ['citizen_report_id' => $report->id, 'worksite_id' => $report->worksite_id, 'status' => $report->status, 'observer_id' => $report->assigned_to],
        );
    }
}
