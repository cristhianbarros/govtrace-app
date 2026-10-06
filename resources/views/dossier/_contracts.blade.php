@foreach ($contracts as $contract)
    <table>
        <tr><th>Contrato (SECOP II)</th><td>{{ $contract['id'] }}</td></tr>
        <tr><th>Objeto</th><td>{{ $contract['object'] }}</td></tr>
        <tr><th>Entidad contratante</th><td>{{ $contract['entity'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Contratista</th><td>{{ $contract['contractor'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Valor</th><td>{{ $contract['value'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Firmado el</th><td>{{ $contract['signed_at'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Fecha de terminación</th><td>{{ $contract['end_date'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Supervisor (según SECOP II)</th><td>{{ $contract['supervisor'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Orden de la entidad</th><td>{{ $contract['entity_order'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Origen de los recursos</th><td>
            @forelse ($contract['funding'] as $source)
                {{ $source['label'] }}: {{ $source['amount'] }}@if (! $loop->last)<br>@endif
            @empty
                Sin dato en SECOP II
            @endforelse
        </td></tr>
        <tr><th>Lugar</th><td>{{ $contract['place'] ?? 'Sin dato en SECOP II' }}</td></tr>
        <tr><th>Estado en SECOP II</th><td>{{ $contract['status'] }}</td></tr>
        @if ($contract['secop_url'])
            <tr><th>En SECOP II</th><td class="hash">{{ $contract['secop_url'] }}</td></tr>
        @endif
    </table>
@endforeach
