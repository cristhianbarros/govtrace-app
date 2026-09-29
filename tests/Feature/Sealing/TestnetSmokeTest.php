<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Sealing\ReportSeal;
use App\Infrastructure\Stellar\StellarRpc;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\ConfirmSeal;
use App\Jobs\SealReport;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Soneso\StellarSDK\Crypto\KeyPair;

/*
 * Iteración 14 — prueba de humo en la testnet de Stellar (R-TST-01, antes de
 * cada salida a producción): un reporte de prueba llega a "Sellada" en
 * testnet, con la comisión pagada por la cuenta patrocinadora (fee bump), y
 * se mide cuánto costó de verdad — el insumo de D12.
 *
 * D12: la hot wallet (la patrocinadora) solo paga la renta de cada sello; la
 * vigencia del contrato la paga la tesorería al desplegar. Por eso el primer
 * sello tras un despliegue cuesta lo mismo que cualquier otro — antes de D12
 * pagaba además esa extensión: 27,5 XLM medidos en testnet.
 *
 * Las llaves llegan como variables de entorno (D11): make smoke-testnet carga
 * .env.testnet, que en desarrollo escribe make testnet-setup y en CI Jenkins,
 * con sus credenciales. make test lo excluye (grupo "testnet").
 */

pest()->group('testnet');

beforeEach(function () {
    Http::allowStrayRequests();
});

it('seals a test report on the Stellar testnet, with the fee paid by the sponsor account', function () {
    expect(config('stellar.network_passphrase'))->toBe('Test SDF Network ; September 2015', 'Esta prueba es contra testnet: córrela con make smoke-testnet.');

    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');

    $network = app(SealingNetwork::class);
    $rpc = app(StellarRpc::class);
    $sealer = KeyPair::fromSeed(config('stellar.sealer_secret'))->getAccountId();
    $sponsor = $network->sponsorAddress();

    $tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');

    try {
        (new ConfigureTerritory)->handle($tenant, ['47']);
        $veedor = reportingMember($tenant, 'carlos@correo.co');
        reportableContract('CO1.PCCNTR.1234567');
        worksiteWithContracts($tenant, ['CO1.PCCNTR.1234567'], santaMartaWorksiteLocation());

        $sealerBefore = $rpc->accountBalance($sealer);

        // Dos reportes seguidos: el primero tras el despliegue y el de
        // régimen. Con D12 los dos cuestan lo mismo.
        $sealReport = function () use ($tenant, $veedor, $rpc, $sponsor): array {
            $sponsorBefore = $rpc->accountBalance($sponsor);
            $reportId = sendReport($veedor)->assertCreated()->json('id');
            app()->call([new SealReport($tenant->id, $reportId), 'handle']);

            // Testnet cierra un ledger cada ~5 s.
            for ($attempt = 0; $attempt < 30; $attempt++) {
                app()->call([new ConfirmSeal($tenant->id, $reportId), 'handle']);
                if ($tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole()->status->label()) === 'Sellada') {
                    break;
                }
                sleep(2);
            }

            return [$tenant->run(fn () => ReportSeal::query()->where('report_id', $reportId)->sole()), $sponsorBefore - $rpc->accountBalance($sponsor)];
        };

        [$first, $firstFee] = $sealReport();
        [$second, $steadyFee] = $sealReport();

        // Se registra antes de afirmar nada, para no perder la medición si
        // algo falla. base_path y no storage_path: la petición dejó activo el
        // contexto de la organización, y storage_path apuntaría a su carpeta.
        $measurement = [
            'network' => 'testnet',
            'measured_at' => now()->toIso8601String(),
            'first_seal' => ['ledger' => $first->ledger, 'tx_hash' => $first->tx_hash, 'fee_stroops' => $firstFee],
            'steady_seal' => ['ledger' => $second->ledger, 'tx_hash' => $second->tx_hash, 'fee_stroops' => $steadyFee],
        ];
        file_put_contents(base_path('storage/logs/testnet-smoke.json'), json_encode($measurement, JSON_PRETTY_PRINT));
        fwrite(STDOUT, sprintf(
            "\n  Sellados en testnet: ledgers %d y %d (tx %s)\n  Primer sello: %d stroops (%.7f XLM) · sello de régimen: %d stroops (%.7f XLM)\n",
            $first->ledger, $second->ledger, $second->tx_hash, $firstFee, $firstFee / 10_000_000, $steadyFee, $steadyFee / 10_000_000,
        ));

        foreach ([$first, $second] as $seal) {
            expect($seal->status->label())->toBe('Sellada')
                ->and($seal->ledger)->toBe($network->findSeal($seal->merkle_root)->ledger);
        }

        // D5 / R-BLK-04: la selladora no paga nada; la patrocinadora, la comisión.
        expect($rpc->accountBalance($sealer))->toBe($sealerBefore);

        // D12: ningún sello, ni el primero, paga la vigencia del contrato.
        // Cota de cordura: menos de 1 XLM cada uno (se miden ~0,25 XLM).
        foreach ([$firstFee, $steadyFee] as $fee) {
            expect($fee)->toBeGreaterThan(0)->toBeLessThan(10_000_000);
        }
    } finally {
        if (tenant()) {
            tenancy()->end();
        }
        Tenant::query()->get()->each->delete();
        DB::table('contracts')->delete();
        DB::table('audit_logs')->delete();
    }
});
