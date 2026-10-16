{{--
    Panoul cu cererile de la tejghea (SDD §5.3, ADR 012). Lista vine randată de server la încărcare și se
    reîmprospătează prin polling (requestPoller); acțiunile merg prin fetch JSON.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-espresso-900">Cereri de ștampile</h2>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-2xl space-y-6 px-4 sm:px-6">
            <x-flash />

            @if ($locations->isEmpty())
                <x-card>
                    <p class="font-semibold text-espresso-900">Adaugă întâi o locație</p>
                    <p class="mt-1 text-sm text-espresso-600">Cererile vin de la QR-ul de pe tejgheaua unei locații. Owner-ul o poate adăuga din pagina Locații.</p>
                </x-card>
            @else
                @include('staff.partials.location-picker')

                @if ($location)
                    <section x-data="requestPoller($el.dataset.feed, {{ Js::from($feed) }}, {{ $pollSeconds }})"
                             data-feed="{{ route('staff.requests.feed') }}" aria-label="Cereri în așteptare" class="space-y-4">
                        <p x-show="message" x-cloak x-text="message" role="status"
                           class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-900"></p>
                        <p x-show="error" x-cloak x-text="error" role="alert"
                           class="rounded-2xl border border-red-200 bg-red-50 p-3 text-sm text-red-900"></p>

                        <x-card x-show="requests.length === 0">
                            <div class="flex items-center gap-4">
                                <span class="h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-gold-500" aria-hidden="true"></span>
                                <div>
                                    <p class="font-semibold text-espresso-900">Nicio cerere acum</p>
                                    <p class="mt-1 text-sm text-espresso-600">Cererile de la tejghea apar aici automat, la câteva secunde.</p>
                                </div>
                            </div>
                        </x-card>

                        {{-- Prima randare fără JS: aceleași date pe care le folosește componenta. --}}
                        <noscript>
                            @foreach ($feed as $row)
                                <p>{{ $row['customer'] }} · {{ $row['program'] }}</p>
                            @endforeach
                        </noscript>

                        <ul class="space-y-3">
                            <template x-for="request in requests" :key="request.id">
                                <li class="rounded-3xl bg-white p-5 shadow-xl shadow-espresso-950/5">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <p class="truncate font-bold text-espresso-950" x-text="request.customer"></p>
                                            <p class="truncate text-sm text-espresso-500" x-text="request.email"></p>
                                            <p class="mt-1 text-sm text-espresso-700">
                                                <span x-text="request.program"></span> ·
                                                <span x-text="`${request.stamps} din ${request.required}`"></span>
                                                <span x-show="request.rewards > 0" class="font-semibold text-gold-700"
                                                      x-text="` · ${request.rewards} ${request.rewards === 1 ? 'recompensă' : 'recompense'}`"></span>
                                            </p>
                                        </div>
                                        <span class="shrink-0 text-xs text-espresso-500" x-text="age(request)"></span>
                                    </div>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <template x-for="quantity in quantities(request)" :key="quantity">
                                            <button type="button" @click="approve(request, quantity)" :disabled="acting !== null"
                                                    :class="quantity === 1 ? 'btn-gold flex-1' : 'btn-outline'" class="justify-center py-3"
                                                    x-text="quantity === 1 ? 'Aprobă +1' : `+${quantity}`"></button>
                                        </template>
                                        <button type="button" @click="reject(request)" :disabled="acting !== null" class="btn-outline justify-center py-3">
                                            Respinge
                                        </button>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </section>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
