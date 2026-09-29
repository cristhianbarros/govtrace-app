<?php

use App\Domain\Auth\Notifications\ResetPasswordLink;
use App\Domain\Organization\Notifications\WelcomeNotification;
use App\Domain\Organization\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Validator;

/*
 * Iteración 41 — la aplicación, en español también donde habla Laravel: los
 * correos se armaban con su plantilla en inglés ("Regards", "If you're having
 * trouble clicking…", "All rights reserved"), y los mensajes de validación por
 * defecto salían en inglés si alguien se saltaba la pantalla (deuda aceptada
 * hasta la it. 41).
 */

const ENGLISH_MAIL_LINES = ['Regards', 'Hello!', 'Whoops!', 'having trouble clicking', 'copy and paste the URL', 'All rights reserved'];

function renderedMail(Notification $notification): string
{
    return (string) $notification->toMail(new User(['name' => 'Ana Torres', 'email' => 'ana@correo.co']))->render();
}

it('writes the emails of GovTrace in Spanish, without the English lines of the template', function (Notification $notification, string $button) {
    $mail = renderedMail($notification);

    foreach (ENGLISH_MAIL_LINES as $english) {
        expect($mail)->not->toContain($english);
    }
    expect($mail)->toContain('Saludos,')
        ->toContain("Si el botón «{$button}» no funciona, copie y pegue esta dirección en su navegador:")
        ->toContain('Todos los derechos reservados.');
})->with([
    'la bienvenida y la invitación' => [fn () => new WelcomeNotification('https://veeduria-smr.govtrace.example/set-password/1?token=abc', 48), 'Establecer mi contraseña'],
    'la recuperación de contraseña' => [fn () => new ResetPasswordLink('https://veeduria-smr.govtrace.example/reset-password/abc?email=ana%40correo.co', 'abc'), 'Elegir una nueva contraseña'],
]);

it('answers the default validation messages in Spanish', function () {
    $errors = Validator::make(
        ['correo' => 'no-es-un-correo', 'fotos' => 'x'],
        ['correo' => 'required|email', 'nombre' => 'required', 'fotos' => 'array'],
    )->errors();

    expect($errors->first('correo'))->toBe('El campo correo debe ser una dirección de correo electrónico válida.')
        ->and($errors->first('nombre'))->toBe('El campo nombre es obligatorio.')
        ->and($errors->first('fotos'))->toBe('El campo fotos debe ser una lista.');
});

it('speaks Spanish by default: the pages say lang="es"', function () {
    expect(config('app.locale'))->toBe('es')
        ->and($this->withoutVite()->get('http://govtrace.localhost/login')->getContent())->toContain('<html lang="es">');
});
