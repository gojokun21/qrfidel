@props(['numeric' => false])

<td {{ $attributes->class(['px-5 py-3', 'text-right tabular-nums' => $numeric]) }}>{{ $slot }}</td>
