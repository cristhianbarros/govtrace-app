<?php

use App\Application\Demo\DemoEnvironment;
use App\Application\Demo\PrepareDemo;
use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Sealing\SealingNetwork;
use App\Domain\Audit\AuditLog;
use App\Domain\Contracts\Contract;
use App\Domain\Geography\Department;
use App\Domain\Organization\User as OrganizationUser;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\JpegMetadata;
use App\Domain\Reports\Report;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use App\Jobs\ConfirmSeal;
use App\Jobs\SealReport;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\FakeSealingNetwork;

/*
 * Iteración 38 — make demo: una organización de demostración completa para
 * recorrer todas las pantallas sin depender de internet ni de SECOP: su
 * Administrador y dos veedores con contraseñas conocidas, contratos y obras
 * de Magdalena (una sin ubicación y una con dos contratos agrupados),
 * reportes con fotos que pasan por el mismo camino que los de un veedor
 * —CreateReport, hash, sellado— y, ya sellados, unos publicados y otros que
 * esperan en la bandeja para publicarlos en vivo.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const DEMO_DOMAIN = 'veeduria-demo.govtrace.localhost';

beforeEach(function () {
    $this->artisan('migrate');
    Storage::fake('evidencias');
    $this->network = new FakeSealingNetwork;
    app()->instance(SealingNetwork::class, $this->network);
    config(['app.url' => 'http://govtrace.localhost:8080']);
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    User::query()->where('email', 'like', '%@demo.govtrace.test')->delete();
});

/** The worker's job, in the test: the reports go on to "Sellada". */
function sealOnFakeNetwork(): Closure
{
    return function (Tenant $tenant, array $reportIds): void {
        foreach ($reportIds as $reportId) {
            app()->call([new SealReport($tenant->id, $reportId), 'handle']);
            app()->call([new ConfirmSeal($tenant->id, $reportId), 'handle']);
        }
        tenancy()->end();
    };
}

function demo(?Closure $awaitSeals = null): DemoEnvironment
{
    return (new PrepareDemo($awaitSeals ?? sealOnFakeNetwork()))->handle();
}

it('leaves the demo organization at its own subdomain, watching Magdalena', function () {
    $environment = demo();

    expect($environment->organization->domains()->value('domain'))->toBe(DEMO_DOMAIN)
        ->and($environment->url)->toBe('http://'.DEMO_DOMAIN.':8080')
        ->and(Department::query()->where('code', '47')->exists())->toBeTrue() // DIVIPOLA, cargada por la demo
        ->and(DB::table('organization_territories')->where('tenant_id', $environment->organization->id)->exists())->toBeTrue();
});

it('gives everyone a known password: the Super Administrator, the Administrador and two veedores', function () {
    $environment = demo();

    expect(array_column($environment->credentials, 'role'))->toBe(['Super Administrador', 'Administrador de la organización', 'Veedor', 'Veedor']);

    foreach ($environment->credentials as $credential) {
        $host = $credential['role'] === 'Super Administrador' ? 'http://govtrace.localhost:8080' : $environment->url;
        $this->flushSession();
        $this->post("{$host}/login", ['email' => $credential['email'], 'password' => $credential['password']])
            ->assertRedirect();
    }
});

it('has contracts and worksites to show: one without location, one grouping two contracts', function () {
    $environment = demo();

    $environment->organization->run(function () {
        $withTwo = Worksite::query()->withCount('contracts')->get()->filter(fn ($worksite) => $worksite->contracts_count === 2);

        expect(Worksite::query()->count())->toBe(6)
            ->and(Worksite::query()->whereNull('latitude')->count())->toBe(1)
            ->and($withTwo)->toHaveCount(1);
    });

    expect(Contract::query()->where('secop_contract_id', 'like', 'CO1.PCCNTR.91%')->count())->toBe(7)
        ->and(Contract::query()->where('secop_contract_id', 'like', 'CO1.PCCNTR.91%')->distinct('municipality_code')->count('municipality_code'))->toBe(3);
});

it('sends its reports through CreateReport, with photos that are all different and carry no metadata', function () {
    $environment = demo();

    $environment->organization->run(function () {
        $evidences = Evidence::query()->get();

        expect(Report::query()->count())->toBe(9)
            ->and($evidences->pluck('sha256')->unique())->toHaveCount($evidences->count());

        foreach ($evidences as $evidence) {
            $bytes = Storage::disk('evidencias')->get($evidence->storage_path);
            expect(str_starts_with($bytes, "\xFF\xD8"))->toBeTrue()
                ->and(hash('sha256', $bytes))->toBe($evidence->sha256);

            $path = tempnam(sys_get_temp_dir(), 'demo-');
            file_put_contents($path, $bytes);
            expect(JpegMetadata::carriesMetadata($path))->toBeFalse();
            unlink($path);
        }
    });
});

it('waits for the seals, then publishes six evidences and leaves three in the inbox for the live demo', function () {
    $environment = demo();

    $environment->organization->run(function () {
        expect(ReportSeal::query()->where('status', SealStatus::Sealed)->count())->toBe(9)
            ->and(Report::query()->where('editorial_status', 'published')->count())->toBe(6)
            ->and(Report::query()->where('editorial_status', 'hidden')->count())->toBe(3);
    });

    expect($environment->pendingSeals)->toBe(0)
        ->and($environment->published)->toBe(6)
        ->and(AuditLog::query()->where('action', 'evidence.published')->where('organization_id', $environment->organization->id)->count())->toBe(6);
});

it('sends every report at once, as a burst, and waits for the network to seal them all before publishing', function () {
    $asked = [];
    $seal = sealOnFakeNetwork();

    demo(function (Tenant $tenant, array $reportIds) use (&$asked, $seal) {
        // It. 39: la selladora los toma por turnos; la demostración ya no los espacia.
        $asked[] = [$reportIds, $tenant->run(fn () => Report::query()->count())];
        $seal($tenant, $reportIds);
    });

    expect($asked)->toHaveCount(1)
        ->and($asked[0][0])->toHaveCount(9)
        ->and($asked[0][1])->toBe(9);
});

it('publishes only what got sealed in time, and says how many are still waiting', function () {
    $seal = sealOnFakeNetwork();

    // Solo 4 alcanzan a sellarse mientras la demostración espera.
    $environment = demo(fn (Tenant $tenant, array $reportIds) => $seal($tenant, array_slice($reportIds, 0, 4)));

    $environment->organization->run(fn () => expect(Report::query()->where('editorial_status', 'published')->count())->toBe(4));
    expect($environment->published)->toBe(4)
        ->and($environment->pendingSeals)->toBe(5);
});

it('publishes nothing when the seals do not arrive, and says how many are still waiting', function () {
    config(['demo.seal_wait_seconds' => 0]);

    $environment = (new PrepareDemo)->handle();

    $environment->organization->run(function () {
        expect(Report::query()->where('editorial_status', 'published')->count())->toBe(0)
            ->and(ReportSeal::query()->where('status', SealStatus::Queued)->count())->toBe(9);
    });

    expect($environment->pendingSeals)->toBe(9);
});

it('can be run again: it starts over, without duplicating the organization or the Super Administrator', function () {
    demo();
    $second = demo();

    expect(Tenant::query()->count())->toBe(1)
        ->and(User::query()->where('email', 'admin@demo.govtrace.test')->count())->toBe(1);

    $second->organization->run(fn () => expect(Report::query()->count())->toBe(9)
        ->and(OrganizationUser::query()->count())->toBe(3));
});

it('never touches an organization registered by hand, even one that uses the same name or watches the same territory', function () {
    $real = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta (demo)', 'veeduria-real');
    (new ConfigureTerritory)->handle($real, ['47']);

    demo();
    demo();

    expect(Tenant::query()->whereKey($real->id)->exists())->toBeTrue()
        ->and($real->domains()->value('domain'))->toBe('veeduria-real.govtrace.localhost')
        ->and(Tenant::query()->count())->toBe(2);
});

it('never runs in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => demo())->toThrow(RuntimeException::class, 'La demostración no corre en producción');
    expect(Tenant::query()->count())->toBe(0);
});

it('make demo: shows where to go and who to log in as', function () {
    $output = new BufferedOutput;

    expect(Artisan::call('demo:prepare', ['--seal-wait' => 0], $output))->toBe(0);

    expect($output->fetch())
        ->toContain('http://veeduria-demo.govtrace.localhost:8080')
        ->toContain('admin@demo.govtrace.test  /  Demo#2026Global')
        ->toContain('luis.mendoza@correo.co  /  Demo#2026Veedor')
        ->toContain('9 reportes todavía no llegan a «Sellada» (publicados: 0)');
});
