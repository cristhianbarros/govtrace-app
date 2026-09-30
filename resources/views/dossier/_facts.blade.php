{{-- Los hechos: lo que dice SECOP II y lo que registraron los veedores en sus evidencias publicadas. --}}
<ol>
    @foreach ($contracts as $contract)
        @if ($contract['overdue'])
            <li>El contrato {{ $contract['id'] }} terminaba el {{ $contract['end_date'] }}, y SECOP II lo sigue mostrando «{{ $contract['status'] }}».</li>
        @endif
    @endforeach
    @foreach ($evidences as $evidence)
        <li>El {{ $evidence['captured_at'] }}, un veedor de esta veeduría estuvo en la obra y registró «{{ $evidence['classification'] }}»@if ($evidence['comment']): «{{ rtrim($evidence['comment'], ' .') }}»@endif. Es la evidencia n.º {{ $evidence['number'] }} de los anexos.</li>
    @endforeach
    @if (count($evidences) === 0 && collect($contracts)->where('overdue', true)->isEmpty())
        <li>Esta veeduría vigila la ejecución del contrato y necesita la información que aquí pide para cumplir su función.</li>
    @endif
</ol>
