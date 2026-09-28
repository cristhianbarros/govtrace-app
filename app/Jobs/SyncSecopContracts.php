<?php

namespace App\Jobs;

use App\Application\Contracts\ProcessSecopContractRow;
use App\Application\Contracts\SecopRowOutcome;
use App\Domain\Contracts\SecopSyncRun;
use App\Domain\Organization\WatchedTerritories;
use App\Infrastructure\Secop\SecopClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * US-013: consulta SECOP II solo por los territorios de las
 * organizaciones activas. Dos formas de dispararse:
 * - de noche, a la hora configurada (routes/console.php), sin argumentos:
 *   todo el territorio vigilado;
 * - de inmediato, con el id de una organización, cuando se da de alta o
 *   cambia su territorio (RegisterOrganization, ConfigureTerritory — la
 *   reactivación de US-003a, it. 20, debe despacharlo también).
 */
class SyncSecopContracts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @param  string|null  $organizationId  null = todas las organizaciones activas.
     */
    public function __construct(public readonly ?string $organizationId = null) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(SecopClient $client, ProcessSecopContractRow $processor): void
    {
        $run = SecopSyncRun::create([
            'organization_id' => $this->organizationId,
            'started_at' => now(),
            'status' => 'running',
        ]);

        $inserted = 0;
        $updated = 0;
        $unmatched = [];
        $error = null;

        try {
            $watched = WatchedTerritories::ofActiveOrganizations($this->organizationId);

            foreach ($watched->departmentsToQuery() as $department) {
                foreach ($client->fetchWorksContracts($department->secopName()) as $row) {
                    $outcome = $processor->handle($row, $watched);

                    if ($outcome === SecopRowOutcome::Inserted) {
                        $inserted++;
                    } elseif ($outcome === SecopRowOutcome::Updated) {
                        $updated++;
                    } elseif ($outcome === SecopRowOutcome::Unmatched) {
                        $location = ($row['departamento'] ?? '?').' / '.($row['ciudad'] ?? '?');
                        $unmatched[$location] = ($unmatched[$location] ?? 0) + 1;
                    }
                }
            }
        } catch (Throwable $e) {
            $error = $e;
        }

        $run->update([
            'status' => $error ? 'failed' : 'success',
            'finished_at' => now(),
            'contracts_inserted' => $inserted,
            'contracts_updated' => $updated,
            'contracts_discarded' => array_sum($unmatched),
            'unmatched_locations' => $unmatched ?: null,
            'error_message' => $error?->getMessage(),
        ]);

        if ($error) {
            throw $error; // la cola reintenta según $tries y backoff()
        }
    }
}
