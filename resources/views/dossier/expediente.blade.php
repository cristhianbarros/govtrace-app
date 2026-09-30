@extends('dossier.layout')
@section('title', 'Expediente de la obra')
@section('content')
    <h1>Expediente de la obra: {{ $worksite['name'] }}</h1>
    <p>Veeduría ciudadana «{{ $organization['name'] }}» ({{ $organization['identification'] }}) · Generado el {{ $generated_at }} con GovTrace ({{ $organization['url'] }}).</p>

    <h2>1. Contratos de la obra en SECOP II</h2>
    @include('dossier._contracts')

    <h2>2. Estado de la obra en GovTrace</h2>
    <p><strong>{{ $condition['label'] }}.</strong> {{ $condition['reason'] }}</p>
    <p class="small">Es una alerta de GovTrace, calculada con los datos de SECOP II y las evidencias publicadas. No es la decisión de una autoridad, ni quiere decir que sea una «obra inconclusa» en el sentido de la Ley 2020 de 2020.</p>

    <h2>3. Evidencias publicadas</h2>
    @forelse ($evidences as $evidence)
        <table>
            <tr><th>Evidencia n.º {{ $evidence['number'] }}</th><td>{{ $evidence['classification'] }}</td></tr>
            <tr><th>Tomada el</th><td>{{ $evidence['captured_at'] }}</td></tr>
            <tr><th>Lo que registró el veedor</th><td>{{ $evidence['comment'] ?: 'Sin comentario' }}</td></tr>
            <tr><th>Lugar aproximado (unos 100 m)</th><td>{{ $evidence['place'] }}</td></tr>
            <tr><th>Archivos</th><td>@foreach ($evidence['files'] as $file){{ $file['path'] }}@if (! $loop->last)<br>@endif @endforeach</td></tr>
        </table>
    @empty
        <p>Aún no hay evidencias publicadas de esta obra.</p>
    @endforelse

    @if (count($evidences) > 0)
        @include('dossier._proof')
    @endif

    <h2>Qué trae este expediente</h2>
    <ul>
        <li>«01-expediente.pdf»: este documento.</li>
        <li>«02-derecho-de-peticion.pdf»: la plantilla para pedir información a la entidad contratante (Ley 1755 de 2015).</li>
        <li>«03-denuncia-contraloria.pdf»: la plantilla para denunciar ante la Contraloría (Ley 1757 de 2015, artículo 69).</li>
        <li>«evidencias/»: cada archivo publicado, tal como se selló, con su prueba de inclusión.</li>
    </ul>
@endsection
