{{--
    Ecranul cardului scanat (SDD §5.2). Acțiunile apar doar cu un tichet de scanare valabil ($scan, ADR 011).
    Fiecare formular are cheia lui de idempotency, generată la randare: un dublu-submit o trimite de două ori
    (replay), iar după redirect pagina primește chei noi.
--}}
@php
    $customer = $card->customer;
    $stampable = $program->isActive();
    $maxStamps = $program->quickStampCount();
    $maskedEmail = $customer->maskedEmail();
@endphp

<x-app-layout :title="$customer->name ?: 'Client'" :description="$maskedEmail" width="narrow">
    <x-slot:actions>
        <x-ui.button :href="route('staff.scan')" variant="secondary" icon="qr">Scanează alt card</x-ui.button>
    </x-slot:actions>

    <x-loyalty.card :business="$program->business->name" :name="$program->name" :required="$program->stamps_required"
                    :reward="$program->reward_title" :color="$program->displayColor()" :icon="$program->stamp_icon->value"
                    :filled="$card->stamps_balance" />

    @if ($rewards > 0)
        <x-ui.alert tone="success">
            <span class="font-semibold">{{ $rewards === 1 ? '1 recompensă disponibilă' : $rewards.' recompense disponibile' }}</span>
            · {{ $program->reward_title }}
        </x-ui.alert>
    @endif

    @if ($scan)
        <section class="space-y-3" aria-label="Acțiuni">
            <p class="flex items-center gap-2 text-sm text-ink-secondary">
                <x-ui.icon name="map-pin" class="h-4 w-4 text-primary-ink" />
                {{ $scan->location->name }}
            </p>

            @if (! $stampable)
                <x-ui.alert>Programul nu este activ: nu se mai dau ștampile, dar recompensele câștigate se pot folosi.</x-ui.alert>
            @elseif ($scan->stampUsed)
                <x-ui.alert>Ștampila pentru această scanare a fost dată. Pentru încă una, scanează din nou codul clientului.</x-ui.alert>
            @else
                <div @class(['grid gap-3', 'grid-cols-2' => $maxStamps > 1])>
                    @for ($quantity = 1; $quantity <= $maxStamps; $quantity++)
                        <form method="POST" action="{{ route('staff.cards.stamp', $card) }}" x-data
                              @submit="setTimeout(() => $el.querySelector('button').disabled = true)"
                              @class(['col-span-2' => $quantity === 1 && $maxStamps > 1])>
                            @csrf
                            <input type="hidden" name="ticket" value="{{ $scan->id }}">
                            <input type="hidden" name="quantity" value="{{ $quantity }}">
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                            <x-ui.button :variant="$quantity === 1 ? 'primary' : 'secondary'" size="xl" class="w-full">
                                {{ $quantity === 1 ? '+1 ștampilă' : "+{$quantity} ștampile" }}
                            </x-ui.button>
                        </form>
                    @endfor
                </div>
            @endif

            @if ($rewards > 0 && ! $scan->redeemUsed)
                <form method="POST" action="{{ route('staff.cards.redeem', $card) }}" x-data
                      @submit="if (! confirm({{ Js::from('Folosești recompensa „'.$program->reward_title.'”?') }})) { $event.preventDefault() } else { setTimeout(() => $el.querySelector('button').disabled = true) }">
                    @csrf
                    <input type="hidden" name="ticket" value="{{ $scan->id }}">
                    <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <x-ui.button variant="neutral" size="xl" icon="gift" class="w-full">Folosește recompensa</x-ui.button>
                </form>
            @endif
        </section>
    @else
        <x-ui.card title="Scanează din nou">
            <x-ui.button :href="route('staff.scan')" icon="qr">Deschide scanerul</x-ui.button>
        </x-ui.card>
    @endif

    @if ($transactions->isNotEmpty())
        <x-ui.card flush title="Ultimele mișcări">
            <ul class="divide-y divide-line">
                @foreach ($transactions as $transaction)
                    <li class="flex items-start justify-between gap-4 px-5 py-3 text-sm sm:px-6">
                        <span class="min-w-0">
                            <span class="flex flex-wrap items-center gap-2 font-medium text-ink">
                                <span @class(['line-through decoration-ink-muted' => $transaction->voided_by_id])>
                                    {{ $transaction->type->label() }}@if ($transaction->type === \App\Enums\TransactionType::Stamp) ×{{ $transaction->quantity }}@endif
                                </span>
                                @if ($transaction->voided_by_id)
                                    <x-ui.badge tone="muted">anulată</x-ui.badge>
                                @endif
                            </span>
                            <span class="block break-words text-xs text-ink-muted">
                                {{ $transaction->staff?->name }}@if ($transaction->location) · {{ $transaction->location->name }}@endif
                            </span>
                        </span>
                        <span class="shrink-0 text-right">
                            <time class="block text-xs text-ink-muted" datetime="{{ $transaction->created_at?->toIso8601String() }}">
                                {{ $transaction->created_at?->setTimezone($program->business->timezone)->format('d.m H:i') }}
                            </time>
                            @if (isset($voidable[$transaction->id]))
                                @include('dashboard.transactions._void-form', ['transaction' => $transaction])
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</x-app-layout>
