<?php

namespace App\Http;

/**
 * It. 46f: what a 405 says, in Spanish. Some addresses only answer the app
 * from inside (a POST, like "Obras cercanas", whose location travels in the
 * body and not in the URL, it. 45f); opened in the browser, Laravel used to
 * answer in English. The JSON says it (bootstrap/app.php), and so does the
 * page (resources/views/errors/405.blade.php).
 */
final class MethodNotAllowedMessage
{
    public const TEXT = 'Esta dirección no se abre en el navegador: la usa la app por dentro. Vuelva a la pantalla anterior.';
}
