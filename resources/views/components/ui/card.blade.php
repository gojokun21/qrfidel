@props(['flush' => false, 'title' => null, 'description' => null])

{{--
    Suprafața de bază: fundal alb, bordură, rază de card.
    `flush` = fără padding (liste cu rânduri care își pun singure padding-ul; folosește `divide-y divide-line`).
    `title` / `description` = antetul cardului (h2); slotul `actions` apare în dreapta antetului.
--}}
<div {{ $attributes->class(['rounded-card border border-line bg-surface shadow-card', 'p-5 sm:p-6' => ! $flush, 'overflow-hidden' => $flush]) }}>
    @if ($title)
        <div @class(['flex flex-wrap items-start justify-between gap-3', 'border-b border-line px-5 py-4 sm:px-6' => $flush, 'mb-5' => ! $flush])>
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
                @if ($description)
                    <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
                @endif
            </div>
            {{ $actions ?? '' }}
        </div>
    @endif
    {{ $slot }}
</div>
