@props(['label', 'value', 'icon' => null, 'hint' => null])

{{-- Un indicator (KPI): eticheta, valoarea, un indiciu opțional; slotul = link sub valoare. --}}
<div {{ $attributes->class('flex flex-col rounded-card border border-line bg-surface p-5 shadow-card') }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-ink-muted">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-primary-soft text-primary-ink">
                <x-ui.icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif
    </div>
    <p class="mt-2 text-3xl font-bold tabular-nums tracking-tight text-ink">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-ink-muted">{{ $hint }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-auto pt-3 text-sm">{{ $slot }}</div>
    @endif
</div>
