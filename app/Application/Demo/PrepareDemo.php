<?php

namespace App\Application\Demo;

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Application\Publication\EditorialDecisions;
use App\Application\Reports\CreateReport;
use App\Application\Reports\NewReport;
use App\Domain\Contracts\Contract;
use App\Domain\Geography\GeoPoint;
use App\Domain\Organization\Roles;
use App\Domain\Organization\User;
use App\Domain\Reports\EvidenceUpload;
use App\Domain\Sealing\ReportSeal;
use App\Domain\Sealing\SealStatus;
use App\Domain\Worksites\Worksite;
use App\Infrastructure\Tenancy\Tenant;
use App\Infrastructure\Tenancy\TenantUrl;
use App\Models\User as SuperAdministrator;
use Closure;
use Database\Seeders\DivipolaSeeder;
use RuntimeException;

/**
 * make demo: a whole organization to walk through every screen without the
 * internet or SECOP — its people with known passwords, contracts and
 * worksites of its territory (DemoTerritory: Magdalena, or the Comuna 13 of
 * Medellín), and reports that take the same road as a veedor's:
 * CreateReport, the photo's hash, the seal. They are all sent at once, a
 * burst like the reports a phone keeps offline and sends when the signal
 * comes back: the sealer takes them by turns, one per ledger (it. 39). Once
 * sealed, six are published and three wait in the inbox to be published live.
 *
 * Starting over is safe: it only ever removes the organization at the demo
 * subdomain, never one registered by hand.
 */
class PrepareDemo
{
    public const SUBDOMAIN = 'veeduria-demo';

    private const NIT = '900555111-6';

    private const SUPER_ADMIN_EMAIL = 'admin@demo.govtrace.test';

    private const ADMINISTRATOR_EMAIL = 'admin@veeduria-demo.org';

    /** Ana Torres and Luis Mendoza, as the reports name them (0 and 1). */
    private const VEEDORES = ['ana.torres@correo.co', 'luis.mendoza@correo.co'];

    private const PASSWORDS = ['super' => 'Demo#2026Global', 'admin' => 'Demo#2026Admin', 'veedor' => 'Demo#2026Veedor'];

    /** How many of the reports get published: the rest wait in the inbox. */
    private const PUBLISHED = 6;

    /** @param  (Closure(Tenant, list<int>): void)|null  $awaitSeals  waits while the worker seals those reports; by default, it polls their seals */
    public function __construct(private readonly ?Closure $awaitSeals = null) {}

    private ?GeoPoint $place = null;

    private DemoTerritory $territory;

    /**
     * @param  GeoPoint|null  $place  where the presentation is (make demo LUGAR=…): the anchored worksite goes there
     * @param  DemoTerritory|null  $territory  make demo TERRITORIO=…: Magdalena, by default
     */
    public function handle(?GeoPoint $place = null, ?DemoTerritory $territory = null): DemoEnvironment
    {
        $this->place = $place;
        $this->territory = $territory ?? DemoTerritory::magdalena();

        if (app()->isProduction()) {
            throw new RuntimeException('La demostración no corre en producción: crea datos de mentira.');
        }

        (new DivipolaSeeder)->run();

        SuperAdministrator::query()->updateOrCreate(
            ['email' => self::SUPER_ADMIN_EMAIL],
            ['name' => 'Super Administrador (demo)', 'password' => self::PASSWORDS['super']],
        );

        $this->removePreviousDemo();
        $this->createContracts();

        $tenant = (new RegisterOrganization)->handle(self::NIT, $this->territory->organizationName, self::SUBDOMAIN);
        (new ConfigureTerritory)->handle($tenant, $this->territory->territory);

        $tenant->run(fn () => $this->createPeopleAndWorksites());
        $reportIds = $this->sendReports($tenant);
        $this->waitForTheSeals($tenant, $reportIds);
        [$published, $pending] = $this->publishSealed($tenant, $reportIds);

        return new DemoEnvironment($tenant, $this->url($tenant), $this->credentials($tenant), $published, $pending);
    }

    /** @return array{float, float}|null */
    private function location(string $worksite): ?array
    {
        if ($worksite === $this->territory->anchored && $this->place) {
            return [$this->place->latitude, $this->place->longitude];
        }

        return $this->territory->worksites[$worksite][1];
    }

    /** Only the one at the demo subdomain: a real organization is never touched. */
    private function removePreviousDemo(): void
    {
        Tenant::query()
            ->whereHas('domains', fn ($domains) => $domains->where('domain', self::SUBDOMAIN.'.'.config('tenancy.apex_domain')))
            ->get()
            ->each->delete();
    }

    private function createContracts(): void
    {
        foreach ($this->territory->contracts as $contract) {
            Contract::fromSecop(fn () => Contract::query()->updateOrCreate(['secop_contract_id' => $contract['secop_contract_id']], $contract));
        }
    }

    /** Inside the organization: its people and its worksites. */
    private function createPeopleAndWorksites(): void
    {
        $this->member('Marta Ospina', self::ADMINISTRATOR_EMAIL, Roles::Administrator, self::PASSWORDS['admin']);
        $this->member('Ana Torres', self::VEEDORES[0], Roles::Observer, self::PASSWORDS['veedor']);
        $this->member('Luis Mendoza', self::VEEDORES[1], Roles::Observer, self::PASSWORDS['veedor']);

        foreach ($this->territory->worksites as $key => [$contracts]) {
            $location = $this->location($key);
            $worksite = Worksite::query()->create([
                'latitude' => $location[0] ?? null,
                'longitude' => $location[1] ?? null,
                'located_at' => $location ? now() : null,
            ]);
            foreach ($contracts as $contract) {
                $worksite->contracts()->create(['secop_contract_id' => $contract]);
            }
        }
    }

    /**
     * Every report, one right after the other, oldest capture first.
     *
     * @return list<int> the reports
     */
    private function sendReports(Tenant $tenant): array
    {
        $reportIds = [];

        foreach ($this->territory->reports as $number => [$worksite, $veedor, $classification, $daysAgo, $metersNorth, $comment]) {
            [$contracts, , $photo] = $this->territory->worksites[$worksite];
            [$latitude, $longitude] = $this->location($worksite);

            $draft = new NewReport(
                secopContractId: $contracts[0],
                classification: $classification,
                comment: $comment,
                latitude: $latitude + rad2deg($metersNorth / 6_371_000),
                longitude: $longitude,
                accuracyMeters: 10,
                capturedAt: now()->subDays($daysAgo)->subHours($number + 1),
                files: [],
            );

            $reportIds[] = $tenant->run(fn () => $this->send(User::query()->where('email', self::VEEDORES[$veedor])->firstOrFail(), $draft, $photo, $number + 1));
        }

        return $reportIds;
    }

    private function member(string $name, string $email, Roles $role, string $password): User
    {
        $member = User::query()->create(['name' => $name, 'email' => $email, 'password' => $password]);
        $member->assignRole($role->value);
        if ($role === Roles::Observer) {
            // US-057-LEG: los veedores de la demostración ya declararon no tener impedimentos; el que se invita en vivo lo hace al activar su cuenta.
            $member->forceFill(['impediments_declared_at' => now()])->save();
        }

        return $member;
    }

    /** The same road as a veedor's phone: a photo with its SHA-256, into CreateReport. */
    private function send(User $veedor, NewReport $draft, int $photo, int $number): int
    {
        $path = tempnam(sys_get_temp_dir(), 'govtrace-demo-');
        file_put_contents($path, $this->uniquePhoto($photo, "GovTrace demo · {$draft->secopContractId} · reporte {$number}"));

        try {
            $report = (new CreateReport)->handle($veedor, new NewReport(
                $draft->secopContractId, $draft->classification, $draft->comment,
                $draft->latitude, $draft->longitude, $draft->accuracyMeters, $draft->capturedAt,
                [EvidenceUpload::fromPath($path)],
            ));
        } finally {
            unlink($path);
        }

        return $report->id;
    }

    /**
     * The demo photo with a JPEG comment of its own after the JFIF header, so that
     * each report seals different bytes (a hash is the identity of an evidence). A
     * comment is not EXIF or XMP: R-PRIV-06 still holds.
     */
    private function uniquePhoto(int $photo, string $label): string
    {
        $jpeg = (string) file_get_contents(database_path("seeders/demo/obra-{$photo}.jpg"));
        $afterJfif = 4 + unpack('n', substr($jpeg, 4, 2))[1];

        return substr($jpeg, 0, $afterJfif)."\xFF\xFE".pack('n', strlen($label) + 2).$label.substr($jpeg, $afterJfif);
    }

    /** @param  list<int>  $reportIds */
    private function waitForTheSeals(Tenant $tenant, array $reportIds): void
    {
        if ($this->awaitSeals !== null) {
            ($this->awaitSeals)($tenant, $reportIds);

            return;
        }

        // Por defecto, el worker de la cola hace el trabajo: se espera a que termine con todos (o se acabe el plazo).
        $deadline = time() + (int) config('demo.seal_wait_seconds');
        while ($this->inProgress($tenant, $reportIds) > 0 && time() < $deadline) {
            sleep(1);
        }
    }

    /** @param  list<int>  $reportIds */
    private function inProgress(Tenant $tenant, array $reportIds): int
    {
        return $tenant->run(fn () => ReportSeal::query()
            ->whereIn('report_id', $reportIds)
            ->whereIn('status', [SealStatus::Received, SealStatus::Queued, SealStatus::Transmitting])
            ->count());
    }

    /**
     * Publishes the first reports that reached "Sellada"; the rest wait in the inbox.
     *
     * @param  list<int>  $reportIds
     * @return array{0: int, 1: int} how many it published, and how many of all did not reach "Sellada"
     */
    private function publishSealed(Tenant $tenant, array $reportIds): array
    {
        return $tenant->run(function () use ($reportIds) {
            $sealed = ReportSeal::query()->whereIn('report_id', $reportIds)->where('status', SealStatus::Sealed)->pluck('report_id')->all();
            $administrator = User::query()->where('email', self::ADMINISTRATOR_EMAIL)->firstOrFail();

            $toPublish = array_intersect(array_slice($reportIds, 0, self::PUBLISHED), $sealed);
            foreach ($toPublish as $reportId) {
                (new EditorialDecisions)->publish($administrator, $reportId);
            }

            return [count($toPublish), count($reportIds) - count($sealed)];
        });
    }

    private function url(Tenant $tenant): string
    {
        return rtrim(TenantUrl::to($tenant->domains()->value('domain'), ''), '/');
    }

    /** @return list<array{role: string, url: string, email: string, password: string}> */
    private function credentials(Tenant $tenant): array
    {
        $organization = $this->url($tenant);

        return [
            ['role' => 'Super Administrador', 'url' => rtrim((string) config('app.url'), '/'), 'email' => self::SUPER_ADMIN_EMAIL, 'password' => self::PASSWORDS['super']],
            ['role' => 'Administrador de la organización', 'url' => $organization, 'email' => self::ADMINISTRATOR_EMAIL, 'password' => self::PASSWORDS['admin']],
            ['role' => 'Veedor', 'url' => $organization, 'email' => self::VEEDORES[0], 'password' => self::PASSWORDS['veedor']],
            ['role' => 'Veedor', 'url' => $organization, 'email' => self::VEEDORES[1], 'password' => self::PASSWORDS['veedor']],
        ];
    }
}
