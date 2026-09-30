<?php

namespace App\Application\Dossier;

use App\Application\Publication\InclusionProof;
use App\Application\Publication\WorksiteCondition;
use App\Domain\Contracts\Contract;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\TenantUrl;
use Carbon\CarbonInterface;

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
            'organization' => ['name' => $tenant->name, 'url' => TenantUrl::to($domain, '/')],
            'worksite' => ['id' => $worksite->id, 'name' => $worksite->name ?? $contracts->first()?->object ?? 'Obra sin nombre'],
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
            // US-034: venció su plazo y SECOP II lo sigue mostrando en ejecución.
            'overdue' => $contract->isOverdueInExecution(today()),
        ];
    }

    /** @return array<string, mixed> */
    private function evidence(Report $report, int $number): array
    {
        $seal = $report->seal;
        $place = $report->location()->approximate(); // R-PRIV-02: el lugar, a unos 100 m
        $folder = 'evidencias/'.$report->captured_at->timezone(self::TIMEZONE)->format('Y-m-d').'-reporte-'.$report->id;

        return [
            'number' => $number,
            'report_id' => $report->id,
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

    private function dateTime(CarbonInterface $moment): string
    {
        return $moment->toImmutable()->timezone(self::TIMEZONE)->format('d/m/Y, H:i').' (hora de Colombia)';
    }
}
