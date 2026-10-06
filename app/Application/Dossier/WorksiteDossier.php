<?php

namespace App\Application\Dossier;

use App\Application\Publication\InclusionProof;
use App\Application\Publication\WorksiteCondition;
use App\Domain\Contracts\Contract;
use App\Domain\Geography\Department;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\PlaceName;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use App\Infrastructure\Tenancy\TenantUrl;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * US-056-LEG: lo que lleva el expediente de una obra — sus contratos de
 * SECOP II, su estado en GovTrace y sus evidencias PUBLICADAS (R-LEG-03),
 * cada archivo con su sello y su prueba de inclusión —, ya en palabras,
 * para los documentos que la veeduría presenta ante la entidad contratante
 * y la Contraloría (Ley 850 de 2003, arts. 15 y 16).
 *
 * Dentro de la organización: lee sus reportes.
 */
class WorksiteDossier
{
    private const TIMEZONE = 'America/Bogota';

    /** It. 46j: the six sources SECOP II breaks a contract's value into. */
    private const FUNDING_LABELS = [
        'pgn' => 'Presupuesto General de la Nación',
        'sgp' => 'Sistema General de Participaciones',
        'sgr' => 'Sistema General de Regalías',
        'territorial' => 'Recursos propios del territorio',
        'credit' => 'Recursos de crédito',
        'own' => 'Recursos propios de la entidad',
    ];

    /** @return array<string, mixed> */
    public function of(Worksite $worksite): array
    {
        $tenant = tenant();
        $domain = $tenant->domains()->value('domain');
        $contracts = Contract::query()
            ->whereIn('secop_contract_id', $worksite->contracts()->pluck('secop_contract_id'))
            ->orderBy('secop_contract_id')
            ->get();
        $reports = Report::query()
            ->where('worksite_id', $worksite->id)
            ->onPublicMap()
            ->with(['seal', 'evidences' => fn ($evidences) => $evidences->orderBy('leaf_index')])
            ->orderBy('captured_at')
            ->orderBy('id')
            ->get();

        return [
            'organization' => ['name' => $tenant->name, 'identification' => $this->identification($tenant), 'url' => TenantUrl::to($domain, '/')],
            'worksite' => ['id' => $worksite->public_id, 'name' => $worksite->name ?? $contracts->first()?->object ?? 'Obra sin nombre'],
            'place' => $this->place($worksite, $contracts, $domain),
            'condition' => (new WorksiteCondition)->of($worksite, $contracts),
            'contracts' => $contracts->map(fn (Contract $contract) => $this->contract($contract))->all(),
            'evidences' => $reports->values()->map(fn (Report $report, int $index) => $this->evidence($report, $index + 1))->all(),
            'network' => config('stellar.network_passphrase'),
            'explorer_url' => config('stellar.explorer_url'),
            'verifier_url' => config('app.verifier_url'),
            'validator_url' => TenantUrl::to($domain, '/verify'),
            'generated_at' => $this->dateTime(now()),
        ];
    }

    /** @return array<string, mixed> */
    private function contract(Contract $contract): array
    {
        return [
            'id' => $contract->secop_contract_id,
            'object' => $contract->object,
            'entity' => $contract->entity_name,
            'contractor' => $contract->contractor_name,
            'value' => $contract->value === null ? null : '$ '.number_format((float) $contract->value, 0, ',', '.'),
            'signed_at' => $contract->signed_at?->format('d/m/Y'),
            'end_date' => $contract->end_date?->format('d/m/Y'),
            'status' => $contract->status,
            'secop_url' => $contract->secop_url,
            // It. 46j: lo que califica la Contraloría; solo el nombre del supervisor, nunca su documento.
            'supervisor' => $contract->supervisor_name,
            'entity_order' => $contract->entity_order,
            'funding' => $this->funding($contract->funding_sources),
            'competence' => $this->competence($contract->funding_sources),
            'place' => $this->contractPlace($contract),
            // US-034: venció su plazo y SECOP II lo sigue mostrando en ejecución.
            'overdue' => $contract->isOverdueInExecution(today()),
        ];
    }

    /** @return list<array{label: string, amount: string}> only the sources with money; empty = SECOP II gave none. */
    private function funding(?array $sources): array
    {
        return collect(self::FUNDING_LABELS)
            ->filter(fn (string $label, string $key) => ($sources[$key] ?? 0) > 0)
            ->map(fn (string $label, string $key) => ['label' => $label, 'amount' => '$ '.number_format($sources[$key], 0, ',', '.')])
            ->values()->all();
    }

    /**
     * A guide, not a decision: the Nation's resources (PGN, SGP, SGR) point to
     * the Contraloría General, the territory's own (and credit) to its
     * contraloría, and the General's control prevails (Constitución, art. 267).
     */
    private function competence(?array $sources): string
    {
        $national = (($sources['pgn'] ?? 0) + ($sources['sgp'] ?? 0) + ($sources['sgr'] ?? 0)) > 0;
        $territorial = (($sources['territorial'] ?? 0) + ($sources['credit'] ?? 0) + ($sources['own'] ?? 0)) > 0;

        return match (true) {
            $national && $territorial => 'Con recursos de la Nación y propios del territorio, la Contraloría General de la República y la contraloría de ese territorio; el control de la General es prevalente (Constitución, artículo 267).',
            $national => 'Con recursos de la Nación, la Contraloría General de la República.',
            $territorial => 'Con recursos propios del territorio, la contraloría de ese territorio, sin perjuicio del control prevalente de la Contraloría General (Constitución, artículo 267).',
            default => 'SECOP II no informa el origen de los recursos: pregúntelo a la entidad contratante, o presente la denuncia ante la Contraloría General, que la remitirá a quien corresponda si no es competente (Ley 1755 de 2015, artículo 21).',
        };
    }

    private function contractPlace(Contract $contract): ?string
    {
        $municipality = $contract->municipality_code ? Municipality::query()->find($contract->municipality_code) : null;
        $department = $contract->department_code ? Department::query()->find($contract->department_code) : null;

        return collect([$municipality?->name, $department?->name])->filter()->map(fn (string $name) => PlaceName::forDisplay($name))->join(', ') ?: null;
    }

    /**
     * It. 46j: where the worksite is — the municipality and department of its
     * contracts, and its point at about 100 m (R-PRIV-02: the official point
     * may have been fixed by a veedor's GPS, so never the exact one).
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array{text: ?string, point: ?string, map_url: string}
     */
    private function place(Worksite $worksite, $contracts, string $domain): array
    {
        $point = $worksite->location()?->approximate();

        return [
            'text' => $contracts->map(fn (Contract $contract) => $this->contractPlace($contract))->filter()->unique()->join('; ') ?: null,
            'point' => $point ? "{$point->latitude}, {$point->longitude}" : null,
            'map_url' => TenantUrl::to($domain, '/worksite/'.$worksite->public_id),
        ];
    }

    /** @return array<string, mixed> */
    private function evidence(Report $report, int $number): array
    {
        $seal = $report->seal;
        $place = $report->location()->approximate(); // R-PRIV-02: el lugar, a unos 100 m
        $folder = 'evidencias/'.$report->captured_at->timezone(self::TIMEZONE)->format('Y-m-d').'-reporte-'.$report->public_id;

        return [
            'number' => $number,
            'report_id' => $report->public_id, // it. 46c
            'captured_at' => $this->dateTime($report->captured_at),
            'classification' => $report->classification->value,
            'comment' => $report->comment,
            'place' => "{$place->latitude}, {$place->longitude}",
            'merkle_root' => $seal->merkle_root,
            'tx_hash' => $seal->tx_hash,
            'ledger' => $seal->ledger,
            'contract_id' => $seal->contract_id,
            'sealed_at' => $this->dateTime($seal->sealed_at),
            'sealed_at_utc' => $seal->sealed_at->toImmutable()->utc()->format('Y-m-d H:i:s').' UTC',
            'files' => $report->evidences->map(fn (Evidence $evidence) => [
                'name' => $evidence->downloadName(),
                'sha256' => $evidence->sha256,
                'path' => "{$folder}/{$evidence->downloadName()}",
                'proof_path' => "{$folder}/{$evidence->proofName()}",
                'storage_path' => $evidence->storage_path,
                'proof' => InclusionProof::of($evidence),
            ])->all(),
        ];
    }

    /** R-LEG-06 (it. 44d): "NIT 900123456-8", "inscrita ante Personería de Santa Marta con Resolución 012 de 2026", o los dos. */
    private function identification(Tenant $tenant): string
    {
        return collect([
            $tenant->nit ? "NIT {$tenant->nit}" : null,
            $tenant->registration_number ? "inscrita ante {$tenant->registration_authority} con {$tenant->registration_number}" : null,
        ])->filter()->join(', ');
    }

    private function dateTime(CarbonInterface $moment): string
    {
        return $moment->toImmutable()->timezone(self::TIMEZONE)->format('d/m/Y, H:i').' (hora de Colombia)';
    }
}
