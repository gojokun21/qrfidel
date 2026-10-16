@props(['label' => null, 'description' => null])

{{-- Checkbox cu eticheta lângă (toată zona e clicabilă). --}}
<label class="flex items-start gap-3">
    <input type="checkbox" {{ $attributes->class('mt-0.5 h-4 w-4 rounded border-line-strong text-primary-active shadow-card focus:ring-2 focus:ring-focus focus:ring-offset-2') }}>
    <span>
        <span class="block text-sm font-medium text-ink">{{ $label ?? $slot }}</span>
        @if ($description)
            <span class="block text-sm text-ink-muted">{{ $description }}</span>
        @endif
    </span>
</label>
