{{-- It. 45b: la versión en texto, con el mismo pie que la de HTML. --}}
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            GovTrace · Evidencia ciudadana que nadie puede cambiar.
            Política de tratamiento de datos: {{ config('app.url') }}/privacidad
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
