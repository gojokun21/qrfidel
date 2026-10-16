@props(['value' => null, 'optional' => false])

<label {{ $attributes->class('block text-sm font-medium text-ink') }}>
    {{ $value ?? $slot }}
    @if ($optional)
        <span class="font-normal text-ink-muted">(opțional)</span>
    @endif
</label>
