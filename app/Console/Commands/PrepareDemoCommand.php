<?php

namespace App\Console\Commands;

use App\Application\Demo\DemoTerritory;
use App\Application\Demo\PrepareDemo;
use App\Domain\Geography\GeoPoint;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

/** make demo — the demonstration organization, with what it takes to walk through every screen. */
class PrepareDemoCommand extends Command
{
    protected $signature = 'demo:prepare
        {--seal-wait= : Seconds to wait for the worker to seal the reports (default: DEMO_SEAL_WAIT_SECONDS, 180)}
        {--lugar= : "latitude,longitude" of the presentation: the anchored worksite goes there, so the veedor can report it live}
        {--territorio=magdalena : magdalena (made up) or medellin (the Comuna 13, with real SECOP II contracts)}';

    protected $description = 'Prepare the demonstration organization (starts over if it already exists; never runs in production)';

    public function handle(): int
    {
        try {
            $territory = DemoTerritory::named((string) $this->option('territorio'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $place = null;
        if ($this->option('lugar') !== null) {
            $place = $this->place($this->option('lugar'));
            if ($place === null) {
                $this->error('LUGAR debe ser «latitud,longitud», por ejemplo LUGAR="6.2442,-75.5812".');

                return self::FAILURE;
            }
        }

        if ($this->option('seal-wait') !== null) {
            config(['demo.seal_wait_seconds' => (int) $this->option('seal-wait')]);
        }

        $this->line('Preparando la demostración (los reportes se sellan en la red, puede tardar un poco)…');

        try {
            $demo = (new PrepareDemo)->handle($place, $territory);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Demostración lista: {$demo->url} ({$demo->organization->name})");
        foreach ($demo->credentials as $credential) {
            $this->line(sprintf('  %-34s %s  %s  /  %s', $credential['role'], $credential['url'], $credential['email'], $credential['password']));
        }

        if ($place) {
            $this->newLine();
            $this->line(sprintf('La obra «%s» quedó en %s, %s: el veedor la encuentra en «Obras cercanas» desde ahí.', $territory->anchoredWorksite(), $place->latitude, $place->longitude));
        }

        if ($demo->pendingSeals > 0) {
            $this->newLine();
            $this->warn("{$demo->pendingSeals} reportes todavía no llegan a «Sellada» (publicados: {$demo->published}): ¿está el worker corriendo y la red Stellar arriba (make stellar-up, make contract-deploy)? Vuelve a correr make demo.");
        }

        return self::SUCCESS;
    }

    private function place(string $value): ?GeoPoint
    {
        if (! preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $value, $match)) {
            return null;
        }

        try {
            return new GeoPoint((float) $match[1], (float) $match[2]);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
