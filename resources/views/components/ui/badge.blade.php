@props(['tone' => 'neutral'])

{{-- Etichetă scurtă de stare. tone: neutral · muted · primary · accent · success · warning · danger · inverse. --}}
<span {{ $attributes->class([
    'inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold',
    match ($tone) {
        'muted' => 'bg-surface-muted text-ink-muted',
        'primary' => 'bg-primary-soft text-primary-ink ring-1 ring-inset ring-primary-line',
        'success' => 'bg-success-soft text-success-ink ring-1 ring-inset ring-success-line',
        'warning' => 'bg-warning-soft text-warning-ink ring-1 ring-inset ring-warning-line',
        'danger' => 'bg-danger-soft text-danger-ink ring-1 ring-inset ring-danger-line',
        'inverse' => 'bg-inverse text-accent-hover',
        'accent' => 'bg-accent-soft text-accent-ink ring-1 ring-inset ring-accent-line',
        default => 'bg-surface-muted text-ink',
    },
]) }}>{{ $slot }}</span>
