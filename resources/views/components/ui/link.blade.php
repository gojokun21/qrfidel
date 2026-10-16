@props(['href', 'variant' => 'primary'])

{{-- Link de text (în paragrafe, sub carduri, „← Înapoi”). variant: primary (auriu) · muted (discret). --}}
<a href="{{ $href }}" {{ $attributes->class([
    'rounded-sm font-medium underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2',
    'text-primary-ink hover:text-ink' => $variant === 'primary',
    'text-ink-muted hover:text-ink' => $variant === 'muted',
]) }}>{{ $slot }}</a>
