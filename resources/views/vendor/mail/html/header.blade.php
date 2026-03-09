@props(['url'])
<tr>
<td class="header">
<a href="{{ $url ?? config('app.url') }}" style="display: inline-block; color: #ffffff !important; text-decoration: none;">
{!! $slot !!}
</a>
</td>
</tr>
