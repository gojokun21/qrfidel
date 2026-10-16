@props(['href', 'active' => false])

{{-- O opțiune dintr-un filtru cu pastile (perioade, categorii). Se pun într-un <nav aria-label="…" class="flex flex-wrap gap-2">. --}}
<a href="{{ $href }}" @if ($active) aria-current="page" @endif {{ $attributes->class([
    'rounded-full px-3 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2',
    'bg-inverse text-inverse-fg' => $active,
    'bg-surface-muted text-ink-secondary hover:bg-surface-strong hover:text-ink' => ! $active,
]) }}>{{ $slot }}</a>
