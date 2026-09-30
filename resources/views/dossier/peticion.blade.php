@extends('dossier.layout')
@section('title', 'Derecho de petición')
@section('content')
    @include('dossier._template_note')

    <p>Ciudad y fecha: ________________________________</p>
    <p>Señores<br><strong>{{ $contracts[0]['entity'] ?? '________________________________' }}</strong></p>

    <p><strong>Asunto: Derecho de petición de información sobre {{ count($contracts) === 1 ? 'el contrato' : 'los contratos' }} {{ collect($contracts)->pluck('id')->join(', ', ' y ') }}.</strong></p>

    <p>Yo, ________________________________, con C.C. n.º ________________, en nombre de la veeduría ciudadana «{{ $organization['name'] }}», me dirijo a ustedes en ejercicio del derecho de petición (artículo 23 de la Constitución Política y Ley 1755 de 2015) y de las funciones y los derechos de las veedurías ciudadanas (Ley 850 de 2003, artículos 15, literal f), y 17).</p>

    <h2>El contrato</h2>
    @include('dossier._contracts')

    <h2>Hechos</h2>
    @include('dossier._facts')

    <h2>Peticiones</h2>
    <ol>
        <li>Copia de los informes de supervisión o de interventoría del contrato.</li>
        <li>El estado actual de la ejecución física y financiera de la obra, con su porcentaje de avance.</li>
        <li>El cronograma vigente y las actas de suspensión, reinicio, prórroga o modificación del contrato.</li>
        <li>Las medidas tomadas frente a los hechos descritos.</li>
    </ol>

    <h2>Término para responder</h2>
    <p>Las peticiones de documentos y de información se resuelven dentro de los diez (10) días siguientes a su recepción, y las demás dentro de los quince (15) días (Ley 1755 de 2015, artículo 14). La información que pide una veeduría es de obligatoria respuesta (Ley 850 de 2003, artículo 17).</p>

    @if (count($evidences) > 0)
        <h2>Anexos</h2>
        <p>Las evidencias que registró la veeduría, cada una con su archivo original y su prueba de inclusión.</p>
        @include('dossier._proof')
    @endif

    @include('dossier._signature')
@endsection
