{{-- It. 46j: dónde está la obra. El punto, a unos 100 m (R-PRIV-02): lo pudo fijar el GPS de un veedor. --}}
<table>
    <tr><th>Municipio y departamento</th><td>{{ $place['text'] ?? 'Sin dato en SECOP II' }}</td></tr>
    <tr><th>Punto aproximado (unos 100 m)</th><td>{{ $place['point'] ?? 'La obra aún no tiene ubicación en GovTrace' }}</td></tr>
    <tr><th>La obra en el mapa de GovTrace</th><td class="hash">{{ $place['map_url'] }}</td></tr>
</table>
<p>Dirección o referencia: ________________________________</p>
