@props(['numeric' => false])

<th scope="col" {{ $attributes->class(['px-5 py-3 font-medium', 'text-right' => $numeric]) }}>{{ $slot }}</th>
