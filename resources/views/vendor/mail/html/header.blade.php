@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; font-size: 20px; font-weight: 800; color: #0f172a; text-decoration: none;">
@if (trim($slot) === 'Laravel' || trim($slot) === '')
{{ config('app.name') }}
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
