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

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="truncate text-2xl font-bold tracking-tight text-espresso-900">{{ $customer->name ?: 'Client' }}</h2>
                <p class="truncate text-sm text-espresso-500">{{ $maskedEmail }}</p>
            </div>
            <a href="{{ route('staff.scan') }}" class="btn-outline shrink-0">
                <x-icon name="qr" class="h-4 w-4" />
                Scanează alt card
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-lg space-y-6 px-4 sm:px-6">
            <x-flash />

            <x-loyalty-card :business="$program->business->name" :name="$program->name" :required="$program->stamps_required"
                            :reward="$program->reward_title" :color="$program->displayColor()" :icon="$program->stamp_icon->value"
                            :filled="$card->stamps_balance" />

            @if ($rewards > 0)
                <p class="rounded-2xl bg-gold-100 px-4 py-3 text-center text-sm font-semibold text-gold-800">
                    {{ $rewards === 1 ? '1 recompensă disponibilă' : $rewards.' recompense disponibile' }}
                    · {{ $program->reward_title }}
                </p>
            @endif

            @if ($scan)
                <section class="space-y-3" aria-label="Acțiuni">
                    <p class="flex items-center gap-2 text-sm text-espresso-600">
                        <x-icon name="map-pin" class="h-4 w-4 text-gold-600" />
                        {{ $scan->location->name }}
                    </p>

                    @if (! $stampable)
                        <p class="rounded-2xl border border-espresso-200 bg-cream-50 p-4 text-sm text-espresso-700">
                            Programul nu este activ: nu se mai dau ștampile, dar recompensele câștigate se pot folosi.
                        </p>
                    @elseif ($scan->stampUsed)
                        <p class="rounded-2xl border border-espresso-200 bg-cream-50 p-4 text-sm text-espresso-700">
                            Ștampila pentru această scanare a fost dată. Pentru încă una, scanează din nou codul clientului.
                        </p>
                    @else
                        <div class="grid gap-3 {{ $maxStamps > 1 ? 'grid-cols-2' : '' }}">
                            @for ($quantity = 1; $quantity <= $maxStamps; $quantity++)
                                <form method="POST" action="{{ route('staff.cards.stamp', $card) }}" x-data
                                      @submit="setTimeout(() => $el.querySelector('button').disabled = true)"
                                      class="{{ $quantity === 1 && $maxStamps > 1 ? 'col-span-2' : '' }}">
                                    @csrf
                                    <input type="hidden" name="ticket" value="{{ $scan->id }}">
                                    <input type="hidden" name="quantity" value="{{ $quantity }}">
                                    <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                    <button class="{{ $quantity === 1 ? 'btn-gold' : 'btn-outline' }} w-full justify-center py-4 text-lg">
                                        {{ $quantity === 1 ? '+1 ștampilă' : "+{$quantity} ștampile" }}
                                    </button>
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
                            <button class="btn-dark w-full justify-center py-4 text-lg">
                                <x-icon name="gift" class="h-5 w-5" />
                                Folosește recompensa
                            </button>
                        </form>
                    @endif
                </section>
            @else
                <x-card>
                    <p class="font-semibold text-espresso-900">Scanează din nou</p>
                    <p class="mt-1 text-sm text-espresso-600">Ștampilele și recompensele se dau doar după o scanare recentă a codului clientului.</p>
                    <a href="{{ route('staff.scan') }}" class="btn-gold mt-4">Deschide scanerul</a>
                </x-card>
            @endif

            @if ($transactions->isNotEmpty())
                <x-card flush>
                    <h3 class="px-6 pt-5 text-sm font-semibold text-espresso-900">Ultimele mișcări</h3>
                    <ul class="mt-2 divide-y divide-espresso-100">
                        @foreach ($transactions as $transaction)
                            <li class="flex items-center justify-between gap-4 px-6 py-3 text-sm">
                                <span class="min-w-0">
                                    <span class="block font-medium text-espresso-900 {{ $transaction->voided_by_id ? 'line-through' : '' }}">
                                        {{ $transaction->type->label() }}@if ($transaction->type === \App\Enums\TransactionType::Stamp) ×{{ $transaction->quantity }}@endif
                                    </span>
                                    <span class="block truncate text-xs text-espresso-500">
                                        {{ $transaction->staff?->name }}@if ($transaction->location) · {{ $transaction->location->name }}@endif
                                    </span>
                                </span>
                                <time class="shrink-0 text-xs text-espresso-500" datetime="{{ $transaction->created_at?->toIso8601String() }}">
                                    {{ $transaction->created_at?->setTimezone($program->business->timezone)->format('d.m H:i') }}
                                </time>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
