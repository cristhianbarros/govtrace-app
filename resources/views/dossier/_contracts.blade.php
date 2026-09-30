@foreach ($contracts as $contract)
    <table>
        <tr><th>Contrato (SECOP II)</th><td>{{ $contract['id'] }}</td></tr>
        <tr><th>Objeto</th><td>{{ $contract['object'] }}</td></tr>
        <tr><th>Entidad contratante</th><td>{{ $contract['entity'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Contratista</th><td>{{ $contract['contractor'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Valor</th><td>{{ $contract['value'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Firmado el</th><td>{{ $contract['signed_at'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Fecha de terminación</th><td>{{ $contract['end_date'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Estado en SECOP II</th><td>{{ $contract['status'] }}</td></tr>
        @if ($contract['secop_url'])
            <tr><th>En SECOP II</th><td class="hash">{{ $contract['secop_url'] }}</td></tr>
        @endif
    </table>
@endforeach
