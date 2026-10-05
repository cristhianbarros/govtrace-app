<?php

use App\Http\MethodNotAllowedMessage;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Tenant routes resolve by Host header (InitializeTenancyByDomain
            // reads request()->getHost() directly, no Route::domain() needed),
            // so this one require() covers every organization subdomain.
            require base_path('routes/tenant.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // It. 41: CSP, Permissions-Policy y, por HTTPS, HSTS, en toda respuesta.
        $middleware->append(SecurityHeaders::class);

        // Without a session, to the login of wherever the visitor is: the
        // organization's on its subdomain, or the global panel's.
        $middleware->redirectGuestsTo(fn () => url('/login'));

        // Not auto-registered outside Laravel's classic Kernel (D3, US-005).
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // It. 45a: sin sesión (venció, o la contraseña cambió desde otro equipo), la app
        // lo dice en español; guarda sus reportes pendientes, que solo descarta ante un 422.
        $exceptions->render(fn (AuthenticationException $exception, Request $request) => $request->expectsJson()
            ? response()->json(['message' => 'Su sesión terminó. Vuelva a entrar con su correo y su contraseña.'], 401)
            : null);

        // It. 46f: una dirección que solo usa la app por dentro, abierta con otro método; la página es errors/405.
        $exceptions->render(fn (MethodNotAllowedHttpException $exception, Request $request) => $request->expectsJson()
            ? response()->json(['message' => MethodNotAllowedMessage::TEXT], 405, $exception->getHeaders())
            : null);
    })->create();
