{{-- El nombre lo fijó el usuario (2026-09-30). El anclaje legal: los mensajes de datos de la Ley 527 de 1999. --}}
<h2>Prueba Pericial Criptográfica</h2>
<p>Cada archivo anexo es un mensaje de datos, admisible como medio de prueba (Ley 527 de 1999, artículos 10 y 11). Su integridad (artículo 9) se comprueba así:</p>
<ol>
    <li>Se calcula la huella SHA-256 del archivo anexo: debe ser la que aparece abajo.</li>
    <li>Con su prueba de inclusión (el archivo «.prueba.json» que lo acompaña) se recalcula la raíz de Merkle del reporte.</li>
    <li>Esa raíz está registrada en la red pública Stellar, en la transacción y desde la fecha indicadas. Nadie puede cambiarla ni borrarla: tampoco GovTrace ni la veeduría.</li>
</ol>
<p>Si un solo bit del archivo hubiera cambiado, la huella y la raíz no coincidirían. Cualquier perito, o cualquier ciudadano, puede repetir la comprobación sin depender de GovTrace: con el programa independiente ({{ $verifier_url }}) o en el validador de la veeduría ({{ $validator_url }}).</p>
@foreach ($evidences as $evidence)
    <h3>Evidencia n.º {{ $evidence['number'] }} · {{ $evidence['captured_at'] }} · {{ $evidence['classification'] }}</h3>
    <table>
        @foreach ($evidence['files'] as $file)
            <tr><th>Archivo</th><td>{{ $file['path'] }}<br><span class="hash">SHA-256: {{ $file['sha256'] }}</span></td></tr>
        @endforeach
        <tr><th>Raíz de Merkle</th><td class="hash">{{ $evidence['merkle_root'] }}</td></tr>
        <tr><th>Transacción en Stellar</th><td class="hash">{{ $evidence['tx_hash'] }}@if ($explorer_url)<br>{{ $explorer_url }}/tx/{{ $evidence['tx_hash'] }}@endif</td></tr>
        <tr><th>Ledger</th><td>{{ $evidence['ledger'] }}</td></tr>
        <tr><th>Sellado el</th><td>{{ $evidence['sealed_at'] }} · {{ $evidence['sealed_at_utc'] }}</td></tr>
        <tr><th>Red</th><td>{{ $network }}</td></tr>
        <tr><th>Contrato de sellado</th><td class="hash">{{ $evidence['contract_id'] }}</td></tr>
    </table>
@endforeach
