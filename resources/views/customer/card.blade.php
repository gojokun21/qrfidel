@php
    // Aceeași amprentă o calculează cardPoller din răspunsul /status: când diferă, pagina se reîncarcă.
    $requestStatus = $stampRequest?->effectiveStatus();
    $fingerprint = implode('|', [$card->stamps_balance, $card->rewards_available, $stampRequest?->id ?? '', $requestStatus?->value ?? '']);
@endphp
<x-customer-layout :title="$program->name">
    <div x-data="cardPoller($el.dataset.url, $el.dataset.fingerprint, {{ (int) $pollSeconds }}, {{ $requestStatus === \App\Enums\StampRequestStatus::Pending ? 'true' : 'false' }})"
         data-url="{{ route('customer.cards.status', $card) }}" data-fingerprint="{{ $fingerprint }}" hidden></div>

    <a href="{{ route('customer.cards') }}" class="link inline-flex items-center gap-1 text-sm">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.8 4.2a.75.75 0 0 1 0 1.06L8.06 10l4.74 4.74a.75.75 0 1 1-1.06 1.06l-5.27-5.27a.75.75 0 0 1 0-1.06l5.27-5.27a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
        Cardurile mele
    </a>

    <div class="mt-4">
        <x-flash />
        @include('customer.partials.install-prompt')
    </div>

    <x-loyalty-card :business="$program->business->name" :name="$program->name" :required="$program->stamps_required"
                    :reward="$program->reward_title" :color="$program->displayColor()" :icon="$program->stamp_icon->value"
                    :filled="$card->stamps_balance" />

    @if ($stampRequest)
        @switch($requestStatus)
            @case(\App\Enums\StampRequestStatus::Pending)
                <div class="mt-3 flex items-center gap-3 rounded-2xl border border-gold-200 bg-gold-50 px-4 py-3 text-sm text-gold-900" role="status">
                    <span class="h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-gold-500" aria-hidden="true"></span>
                    <span><strong>Cerere trimisă</strong> – așteaptă confirmarea angajatului. Expiră la {{ $stampRequest->expires_at->setTimezone($program->business->timezone)->format('H:i') }}.</span>
                </div>
                @break
            @case(\App\Enums\StampRequestStatus::Approved)
                <p class="mt-3 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">
                    Cererea a fost aprobată: {{ $stampRequest->quantity === 1 ? 'o ștampilă nouă' : $stampRequest->quantity.' ștampile noi' }}.
                </p>
                @break
            @case(\App\Enums\StampRequestStatus::Rejected)
                <p class="mt-3 rounded-2xl bg-cream-100 px-4 py-3 text-sm font-semibold text-espresso-800" role="status">Cererea a fost respinsă. Întreabă angajatul de la casă.</p>
                @break
            @case(\App\Enums\StampRequestStatus::Expired)
                <p class="mt-3 rounded-2xl bg-cream-100 px-4 py-3 text-sm font-semibold text-espresso-800" role="status">Cererea a expirat. Scanează din nou QR-ul de pe tejghea.</p>
                @break
        @endswitch
    @endif

    @if ($card->rewards_available > 0)
        <p class="mt-3 rounded-2xl bg-gold-100 px-4 py-3 text-center text-sm font-semibold text-gold-800">
            {{ $card->rewards_available === 1 ? '1 recompensă disponibilă' : $card->rewards_available.' recompense disponibile' }}
            · {{ $program->reward_title }}
        </p>
    @endif

    <section class="mt-6 rounded-3xl bg-white p-6 text-center shadow-xl shadow-espresso-950/5" aria-labelledby="qr-title">
        @if ($scannable)
            <h2 id="qr-title" class="font-bold text-espresso-950">Arată codul la casă</h2>
            @if ($program->isActive())
                <p class="mt-1 text-sm text-espresso-600">Angajatul îl scanează și îți adaugă ștampila.</p>
            @else
                <p class="mt-1 text-sm text-espresso-600">Programul e oprit – recompensele rămân valabile.</p>
            @endif

            <div x-data="qrDisplay($el.dataset.url)" data-url="{{ route('customer.cards.qr', $card) }}" class="mt-5">
                <div class="relative mx-auto aspect-square w-full max-w-64">
                    <div x-show="svg" x-html="svg" :class="{ 'opacity-20 blur-sm': expired }"
                         class="h-full w-full transition [&>svg]:h-full [&>svg]:w-full" role="img" aria-label="Codul QR al cardului"></div>
                    <div x-show="!svg && !error" class="h-full w-full animate-pulse rounded-2xl bg-cream-100"></div>
                    <p x-show="expired" x-cloak class="absolute inset-0 flex items-center justify-center p-6 text-sm font-semibold text-espresso-900">
                        <span x-show="offline">Fără conexiune – codul a expirat. Revino online ca să primești altul.</span>
                        <span x-show="!offline">Codul a expirat și nu am putut obține altul. Reîncercăm automat…</span>
                    </p>
                </div>

                <template x-if="shortCode && !expired">
                    <div class="mt-4">
                        <p class="text-xs uppercase tracking-widest text-espresso-500">Sau dictează codul</p>
                        <p class="mt-1 font-mono text-3xl font-bold tracking-[0.2em] text-espresso-950" x-text="formattedCode"></p>
                    </div>
                </template>

                <p class="mt-3 text-xs text-espresso-500" aria-live="polite">
                    <span x-show="svg && !offline && !expired">Codul se schimbă în <span x-text="secondsLeft"></span> s</span>
                    <span x-show="offline && !expired" x-cloak>Fără conexiune – codul rămâne valabil încă puțin.</span>
                </p>
                <p x-show="error" x-cloak x-text="error" class="text-sm font-semibold text-espresso-700"></p>
            </div>
        @else
            <h2 id="qr-title" class="font-bold text-espresso-950">Program oprit</h2>
            <p class="mt-1 text-sm text-espresso-600">{{ $program->business->name }} nu acordă acum ștampile pentru acest program.</p>
        @endif
    </section>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('customer.business', $program->business) }}" class="link">Despre {{ $program->business->name }}</a>
    </p>
</x-customer-layout>
