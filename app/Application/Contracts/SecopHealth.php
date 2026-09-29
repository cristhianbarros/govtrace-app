<?php

namespace App\Application\Contracts;

use App\Domain\Contracts\SecopSyncRun;
use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Support\Str;

/**
 * US-014: the last SECOP II sync, as the Super Administrador's health panel
 * shows it — when it ran (Colombia time), how it ended, what it brought for
 * each organization, what it discarded and, if it failed, why and when the
 * queue retries it.
 */
class SecopHealth
{
    private const TIMEZONE = 'America/Bogota';

    /** @return array<string, mixed>|null null when no sync has run yet */
    public function latest(): ?array
    {
        $run = SecopSyncRun::query()->latest('started_at')->latest('id')->first();

        if ($run === null) {
            return null;
        }

        return [
            'status' => match ($run->status) {
                'success' => 'Success',
                'failed' => 'Failed',
                default => 'Running',
            },
            'healthy' => $run->status !== 'failed',
            'date' => $run->started_at->tz(self::TIMEZONE)->format('d/m/Y'),
            'started_at' => $run->started_at->tz(self::TIMEZONE)->format('H:i'),
            'finished_at' => $run->finished_at?->tz(self::TIMEZONE)->format('H:i'),
            'processed' => $run->contracts_inserted + $run->contracts_updated,
            'inserted' => $run->contracts_inserted,
            'updated' => $run->contracts_updated,
            'discarded' => $run->contracts_discarded,
            'unmatched_locations' => $run->unmatched_locations ?? [],
            'per_organization' => $this->perOrganization($run),
            'message' => $run->status === 'failed' ? $this->failure($run) : null,
        ];
    }

    /** @return list<array{organization: string, inserted: int, updated: int}> */
    private function perOrganization(SecopSyncRun $run): array
    {
        $counts = $run->per_organization ?? [];
        $names = Tenant::query()->whereIn('id', array_keys($counts))->pluck('name', 'id');

        return collect($counts)
            ->map(fn (array $count, string $id) => ['organization' => $names[$id] ?? $id, 'inserted' => $count['inserted'], 'updated' => $count['updated']])
            ->sortBy(fn (array $row) => Str::ascii($row['organization'])) // "Ciénaga" antes que "Ciudadana"
            ->values()
            ->all();
    }

    private function failure(SecopSyncRun $run): string
    {
        $message = "Falla de sincronización con SECOP II: El servicio remoto no respondió (Error {$run->error_message}).";

        if ($run->next_retry_at === null) {
            return "{$message} Sin más reintentos automáticos: la próxima es la corrida programada.";
        }

        $minutes = max(1, (int) ceil(now()->diffInSeconds($run->next_retry_at) / 60));

        return "{$message} Reintento programado en {$minutes} minutos.";
    }
}
