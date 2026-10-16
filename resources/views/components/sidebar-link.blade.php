@props(['active' => false, 'icon' => null])

<a {{ $attributes->merge([
        'class' => 'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition '.($active
            ? 'bg-espresso-800 text-cream-50'
            : 'text-espresso-300 hover:bg-espresso-800/60 hover:text-cream-50'),
    ]) }}@if ($active) aria-current="page"@endif>
    @if ($active)
        <span class="absolute inset-y-2 -left-3 w-1 rounded-r-full bg-gold-500" aria-hidden="true"></span>
    @endif
    @if ($icon)
        <x-icon :name="$icon" class="h-5 w-5 shrink-0 {{ $active ? 'text-gold-500' : 'text-espresso-400 group-hover:text-gold-400' }}" />
    @endif
    <span class="min-w-0">{{ $slot }}</span>
</a>
