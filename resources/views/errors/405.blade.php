{{-- It. 46f: una dirección que solo usa la app por dentro (POST), abierta en el navegador. --}}
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Esta dirección no se abre en el navegador · GovTrace</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-dvh items-center justify-center bg-slate-50 p-4 text-slate-900">
        <main class="flex max-w-md flex-col gap-4 rounded-2xl bg-white p-6 shadow-soft ring-1 ring-slate-900/5">
            <h1 class="font-display text-xl font-semibold text-brand-900">Esta dirección no se abre en el navegador</h1>
            <p class="text-base">{{ \App\Http\MethodNotAllowedMessage::TEXT }}</p>
            <a href="/" class="inline-flex min-h-11 items-center self-start rounded-xl bg-brand-700 px-4 text-base font-semibold text-white">Ir al inicio</a>
        </main>
    </body>
</html>
