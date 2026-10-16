@props(['icon' => null, 'title'])

{{-- Starea goală a unei liste: iconiță, titlu, explicație (slot) și acțiuni (slotul `actions`). --}}
<div {{ $attributes->class('flex flex-col items-center px-6 py-10 text-center') }}>
    @if ($icon)
        <span class="flex h-12 w-12 items-center justify-center rounded-card bg-primary-soft text-primary-ink">
            <x-ui.icon :name="$icon" class="h-6 w-6" />
        </span>
    @endif
    <p @class(['font-semibold text-ink', 'mt-4' => $icon])>{{ $title }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-1 max-w-md text-sm text-ink-muted">{{ $slot }}</div>
    @endif
    @isset($actions)
        <div class="mt-5 flex flex-wrap justify-center gap-3">{{ $actions }}</div>
    @endisset
</div>
