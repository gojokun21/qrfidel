@php
    // Aceeași amprentă o calculează cardPoller din răspunsul /status: când diferă, pagina se reîncarcă.
    $requestStatus = $stampRequest?->effectiveStatus();
    $fingerprint = implode('|', [$card->stamps_balance, $card->rewards_available, $stampRequest?->id ?? '', $requestStatus?->value ?? '']);
@endphp
<x-customer-layout :title="$program->name">
    <div x-data="cardPoller($el.dataset.url, $el.dataset.fingerprint, {{ (int) $pollSeconds }}, {{ $requestStatus === \App\Enums\StampRequestStatus::Pending ? 'true' : 'false' }})"
         data-url="{{ route('customer.cards.status', $card) }}" data-fingerprint="{{ $fingerprint }}" hidden></div>

    <x-ui.link :href="route('customer.cards')" class="inline-block text-sm">← Cardurile mele</x-ui.link>

    <x-layout.flash />
    @include('customer.partials.install-prompt')

    <div class="space-y-3">
        <x-loyalty.card :business="$program->business->name" :name="$program->name" :required="$program->stamps_required"
                        :reward="$program->reward_title" :color="$program->displayColor()" :icon="$program->stamp_icon->value"
                        :filled="$card->stamps_balance" />

        @if ($stampRequest)
            @switch($requestStatus)
                @case(\App\Enums\StampRequestStatus::Pending)
                    <x-loyalty.request-pending>
                        <strong>Cerere trimisă</strong> – așteaptă confirmarea angajatului. Expiră la {{ $stampRequest->expires_at->setTimezone($program->business->timezone)->format('H:i') }}.
                    </x-loyalty.request-pending>
                    @break
                @case(\App\Enums\StampRequestStatus::Approved)
                    <x-ui.alert tone="success">
                        Cererea a fost aprobată: {{ $stampRequest->quantity === 1 ? 'o ștampilă nouă' : $stampRequest->quantity.' ștampile noi' }}.
                    </x-ui.alert>
                    @break
                @case(\App\Enums\StampRequestStatus::Rejected)
                    <x-ui.alert tone="warning">Cererea a fost respinsă. Întreabă angajatul de la casă.</x-ui.alert>
                    @break
                @case(\App\Enums\StampRequestStatus::Expired)
                    <x-ui.alert>Cererea a expirat. Scanează din nou QR-ul de pe tejghea.</x-ui.alert>
                    @break
            @endswitch
        @endif

        @if ($card->rewards_available > 0)
            <x-ui.alert tone="success">
                <span class="font-semibold">{{ $card->rewards_available === 1 ? '1 recompensă disponibilă' : $card->rewards_available.' recompense disponibile' }}</span>
                · {{ $program->reward_title }}
            </x-ui.alert>
        @endif
    </div>

    <x-ui.card class="text-center">
        <section aria-labelledby="qr-title">
            @if ($scannable)
                <h2 id="qr-title" class="font-bold text-ink">Arată codul la casă</h2>
                @unless ($program->isActive())
                    <p class="mt-1 text-sm text-ink-secondary">Programul e oprit – recompensele rămân valabile.</p>
                @endunless

                <div x-data="qrDisplay($el.dataset.url)" data-url="{{ route('customer.cards.qr', $card) }}" class="mt-5">
                    <div class="relative mx-auto aspect-square w-full max-w-64">
                        {{-- Excepție: SVG-ul QR vine din x-html, deci dimensiunea se pune pe copil cu un variant arbitrar. --}}
                        <div x-show="svg" x-html="svg" :class="{ 'opacity-20 blur-sm': expired }"
                             class="h-full w-full transition [&>svg]:h-full [&>svg]:w-full" role="img" aria-label="Codul QR al cardului"></div>
                        <div x-show="!svg && !error" class="h-full w-full animate-pulse rounded-card bg-surface-muted"></div>
                        <p x-show="expired" x-cloak class="absolute inset-0 flex items-center justify-center p-6 text-sm font-semibold text-ink">
                            <span x-show="offline">Fără conexiune – codul a expirat. Revino online ca să primești altul.</span>
                            <span x-show="!offline">Codul a expirat și nu am putut obține altul. Reîncercăm automat…</span>
                        </p>
                    </div>

                    <template x-if="shortCode && !expired">
                        <div class="mt-4">
                            <p class="text-xs uppercase tracking-eyebrow text-ink-muted">Sau dictează codul</p>
                            <p class="mt-1 tabular-nums text-3xl font-bold tracking-eyebrow text-ink" x-text="formattedCode"></p>
                        </div>
                    </template>

                    <p class="mt-3 text-xs text-ink-muted" aria-live="polite">
                        <span x-show="svg && !offline && !expired">Codul se schimbă în <span x-text="secondsLeft"></span> s</span>
                        <span x-show="offline && !expired" x-cloak>Fără conexiune – codul rămâne valabil încă puțin.</span>
                    </p>
                    <p x-show="error" x-cloak x-text="error" class="text-sm font-semibold text-danger" role="alert"></p>
                </div>
            @else
                <h2 id="qr-title" class="font-bold text-ink">Program oprit</h2>
                <p class="mt-1 text-sm text-ink-secondary">{{ $program->business->name }} nu acordă acum ștampile pentru acest program.</p>
            @endif
        </section>
    </x-ui.card>

    <p class="text-center text-sm">
        <x-ui.link :href="route('customer.business', $program->business)">Despre {{ $program->business->name }}</x-ui.link>
    </p>
</x-customer-layout>
