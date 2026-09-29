<?php

namespace App\Console\Commands;

use App\Application\Demo\PrepareDemo;
use Illuminate\Console\Command;
use RuntimeException;

/** make demo — the demonstration organization, with what it takes to walk through every screen. */
class PrepareDemoCommand extends Command
{
    protected $signature = 'demo:prepare {--seal-wait= : Seconds to wait for the worker to seal each report (default: DEMO_SEAL_WAIT_SECONDS, 60)}';

    protected $description = 'Prepare the demonstration organization (starts over if it already exists; never runs in production)';

    public function handle(): int
    {
        if ($this->option('seal-wait') !== null) {
            config(['demo.seal_wait_seconds' => (int) $this->option('seal-wait')]);
        }

        $this->line('Preparando la demostración (los reportes se sellan en la red, puede tardar un poco)…');

        try {
            $demo = (new PrepareDemo)->handle();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Demostración lista: {$demo->url}");
        foreach ($demo->credentials as $credential) {
            $this->line(sprintf('  %-34s %s  %s  /  %s', $credential['role'], $credential['url'], $credential['email'], $credential['password']));
        }

        if ($demo->pendingSeals > 0) {
            $this->newLine();
            $this->warn("{$demo->pendingSeals} reportes todavía no llegan a «Sellada» (publicados: {$demo->published}): ¿está el worker corriendo y la red Stellar arriba (make stellar-up, make contract-deploy)? Vuelve a correr make demo.");
        }

        return self::SUCCESS;
    }
}
