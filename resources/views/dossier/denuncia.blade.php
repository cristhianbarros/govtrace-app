@extends('dossier.layout')
@section('title', 'Denuncia ante la Contraloría')
@section('content')
    @include('dossier._template_note')

    <p>Ciudad y fecha: ________________________________</p>
    <p>Señores<br><strong>CONTRALORÍA GENERAL DE LA REPÚBLICA</strong><br>
        <span class="small">O la contraloría territorial competente: ________________________________</span></p>

    <p><strong>Asunto: Denuncia por presuntas irregularidades en la ejecución {{ count($contracts) === 1 ? 'del contrato' : 'de los contratos' }} {{ collect($contracts)->pluck('id')->join(', ', ' y ') }}.</strong></p>

    <p>Yo, ________________________________, con C.C. n.º ________________, en nombre de la veeduría ciudadana «{{ $organization['name'] }}» ({{ $organization['identification'] }}), con fundamento en el artículo 69 de la Ley 1757 de 2015 y en el artículo 16 de la Ley 850 de 2003, pongo en su conocimiento los siguientes hechos, para que se evalúen dentro del control fiscal.</p>

    <h2>El contrato</h2>
    @include('dossier._contracts')

    <h2>Hechos</h2>
    @include('dossier._facts')

    <h2>Solicitud</h2>
    <ol>
        <li>Que se evalúe esta denuncia y se adelanten las actuaciones de control fiscal que correspondan.</li>
        <li>Que se me informe el trámite que se le dé y su resultado (Ley 1757 de 2015, artículo 70).</li>
    </ol>

    @if (count($evidences) > 0)
        <h2>Pruebas</h2>
        <p>Las evidencias que registró la veeduría, cada una con su archivo original y su prueba de inclusión.</p>
        @include('dossier._proof')
    @endif

    @include('dossier._signature')

    <h2>Dónde radicarla</h2>
    <ul>
        <li>Línea gratuita 199 o 01 8000 910060.</li>
        <li>En línea, en SIPAR: https://denuncie.contraloria.gov.co:8443/sipar/</li>
        <li>Todos los canales: https://www.contraloria.gov.co/atencion-al-ciudadano/denuncias-y-otras-solicitudes-pqrd</li>
    </ul>
    <p class="small">La Contraloría General atiende las denuncias sobre recursos nacionales. Si la obra se paga con recursos del departamento o del municipio, puede ser competente la contraloría de ese territorio.</p>
@endsection
