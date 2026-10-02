<?php

namespace App\Jobs;

use App\Domain\Organization\Roles;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Sealing\Notifications\SealingQueueStalled;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Infrastructure\Tenancy\Tenant;
use App\Models\User as SuperAdmin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Notification;

/**
 * US-021: cada 15 minutos, busca evidencias con más de 2 horas sin sellar —
 * recibidas, en cola o transmitiendo — y avisa al Super Administrador y al
 * Administrador de cada organización afectada. Una sola alerta por
 * evidencia (stuck_alerted_at): no una cada 15 minutos mientras siga ahí.
 * Las de "Falla de Sellado" ya tienen su banner.
 */
class CheckSealingQueue implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const STALLED_AFTER_MINUTES = 120;

    public function handle(): void
    {
        $affected = [];

        foreach (Tenant::query()->orderBy('name')->get() as $tenant) {
            $tenant->run(function () use ($tenant, &$affected) {
                $stalled = ReportSeal::query()
                    ->whereIn('status', [SealStatus::Received, SealStatus::Queued, SealStatus::Transmitting])
                    ->where('received_at', '<=', now()->subMinutes(self::STALLED_AFTER_MINUTES))
                    ->whereNull('stuck_alerted_at');

                if (! $stalled->exists()) {
                    return;
                }

                $stalled->update(['stuck_alerted_at' => now()]);

                $administrators = OrganizationUser::role(Roles::Administrator->value, 'tenant')->where('is_active', true)->get();
                Notification::send($administrators, new SealingQueueStalled([$tenant->displayName()]));

                $affected[] = $tenant->displayName();
            });
        }

        if ($affected !== []) {
            // It. 46a: solo los Super Administradores activos.
            Notification::send(SuperAdmin::query()->active()->get(), new SealingQueueStalled($affected));
        }
    }
}
