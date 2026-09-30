<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#0f172a">
        @if (tenancy()->initialized)
        {{-- It. 43b (V6): la app del veedor se instala en el celular, con el nombre de su veeduría. --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/pwa/govtrace-192.png">
        @endif
        <link rel="icon" href="/pwa/govtrace.svg" type="image/svg+xml">
        <title inertia>{{ config('app.name', 'GovTrace') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
