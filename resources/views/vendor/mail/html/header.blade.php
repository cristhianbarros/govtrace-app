{{-- It. 45b: el nombre de GovTrace y su frase, en vez del logo de Laravel. --}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<span class="brand">{!! $slot !!}</span>
</a>
<p class="tagline">Veeduría ciudadana de obras públicas</p>
</td>
</tr>
