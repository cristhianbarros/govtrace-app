<?php

namespace App\Jobs;

use App\Application\Contracts\ProcessSecopContractRow;
use App\Application\Contracts\SecopRowOutcome;
use App\Domain\Contracts\Contract;
use App\Domain\Contracts\SecopSyncRun;
use App\Domain\Organization\WatchedTerritories;
use App\Infrastructure\Secop\SecopClient;
use App\Infrastructure\Tenancy\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
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
        $perOrganization = [];
        $error = null;

        try {
            $watched = WatchedTerritories::ofActiveOrganizations($this->organizationId);
            $territories = $this->territoriesByOrganization();

            foreach ($watched->departmentsToQuery() as $department) {
                foreach ($client->fetchWorksContracts($department->secopName()) as $row) {
                    $outcome = $processor->handle($row, $watched);

                    if ($outcome === SecopRowOutcome::Inserted || $outcome === SecopRowOutcome::Updated) {
                        $outcome === SecopRowOutcome::Inserted ? $inserted++ : $updated++;
                        $this->countFor($perOrganization, $territories, $row, $outcome === SecopRowOutcome::Inserted ? 'inserted' : 'updated');
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
            'per_organization' => $perOrganization ?: null,
            'error_message' => $error ? self::describe($error) : null,
            'next_retry_at' => $error ? $this->nextRetryAt() : null,
        ]);

        if ($error) {
            throw $error; // la cola reintenta según $tries y backoff()
        }
    }

    /**
     * US-014: the territory of each active organization this run covers, to
     * count how many contracts each one got.
     *
     * @return array<string, WatchedTerritories>
     */
    private function territoriesByOrganization(): array
    {
        $organizations = Tenant::query()
            ->where('status', 'active')
            ->when($this->organizationId, fn ($query) => $query->whereKey($this->organizationId))
            ->pluck('id');

        return $organizations->mapWithKeys(fn (string $id) => [$id => WatchedTerritories::ofActiveOrganizations($id)])->all();
    }

    /**
     * @param  array<string, array{inserted: int, updated: int}>  $perOrganization
     * @param  array<string, WatchedTerritories>  $territories
     * @param  array<string, mixed>  $row
     */
    private function countFor(array &$perOrganization, array $territories, array $row, string $what): void
    {
        $contract = Contract::query()->where('secop_contract_id', $row['id_contrato'])->first(['department_code', 'municipality_code']);

        foreach ($territories as $organizationId => $territory) {
            if ($contract && $territory->coversLocation($contract->department_code, $contract->municipality_code)) {
                $perOrganization[$organizationId] ??= ['inserted' => 0, 'updated' => 0];
                $perOrganization[$organizationId][$what]++;
            }
        }
    }

    /** "HTTP 504 Gateway Timeout", not Laravel's "HTTP request returned status code 504: …" (US-014). */
    private static function describe(Throwable $error): string
    {
        if ($error instanceof RequestException) {
            $status = $error->response->status();

            return trim("HTTP {$status} ".(SymfonyResponse::$statusTexts[$status] ?? ''));
        }

        return $error instanceof ConnectionException ? 'Sin conexión con SECOP II' : $error->getMessage();
    }

    /** When the queue retries this run, per backoff(); null after the last try. */
    private function nextRetryAt(): ?CarbonInterface
    {
        $attempt = $this->attempts();

        return $attempt < $this->tries ? now()->addSeconds($this->backoff()[$attempt - 1]) : null;
    }
}
