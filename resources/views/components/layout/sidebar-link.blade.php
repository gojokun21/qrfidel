@props(['active' => false, 'icon' => null])

{{-- Un link din sidebar. Cel activ are aria-current="page" și bara de accent din stânga. --}}
<a {{ $attributes->class([
        'group relative flex items-center gap-3 rounded-control px-3 py-2.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-focus',
        'bg-inverse-raised text-inverse-fg' => $active,
        'text-inverse-muted hover:bg-inverse-raised/60 hover:text-inverse-fg' => ! $active,
    ]) }}@if ($active) aria-current="page"@endif>
    @if ($active)
        <span class="absolute inset-y-2 -left-3 w-1 rounded-r-full bg-accent" aria-hidden="true"></span>
    @endif
    @if ($icon)
        <x-ui.icon :name="$icon" @class(['h-5 w-5 shrink-0', 'text-accent' => $active, 'text-inverse-muted group-hover:text-accent-hover' => ! $active]) />
    @endif
    <span class="min-w-0">{{ $slot }}</span>
</a>
