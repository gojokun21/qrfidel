{{--
    Panoul cu cererile de la tejghea (SDD §5.3, ADR 012). Lista vine randată de server la încărcare și se
    reîmprospătează prin polling (requestPoller); acțiunile merg prin fetch JSON.
--}}
<x-app-layout title="Cereri de ștampile" width="form">
    @if ($locations->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="map-pin" title="Adaugă întâi o locație">
                Cererile vin de la QR-ul de pe tejgheaua unei locații. Owner-ul o poate adăuga din pagina Locații.
            </x-ui.empty-state>
        </x-ui.card>
    @else
        @include('staff.partials.location-picker')

        @if ($location)
            <section x-data="requestPoller($el.dataset.feed, {{ Js::from($feed) }}, {{ $pollSeconds }})"
                     data-feed="{{ route('staff.requests.feed') }}" aria-label="Cereri în așteptare" class="space-y-4">
                <x-ui.alert tone="success" x-show="message" x-cloak><span x-text="message"></span></x-ui.alert>
                <x-ui.alert tone="danger" x-show="error" x-cloak><span x-text="error"></span></x-ui.alert>

                <x-ui.card x-show="requests.length === 0">
                    <div class="flex items-center gap-4">
                        <span class="h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-primary" aria-hidden="true"></span>
                        <div>
                            <p class="font-semibold text-ink">Nicio cerere acum</p>
                            <p class="mt-1 text-sm text-ink-muted">Cererile de la tejghea apar aici automat, la câteva secunde.</p>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Prima randare fără JS: aceleași date pe care le folosește componenta. --}}
                <noscript>
                    @foreach ($feed as $row)
                        <p>{{ $row['customer'] }} · {{ $row['program'] }}</p>
                    @endforeach
                </noscript>

                <ul class="space-y-3">
                    <template x-for="request in requests" :key="request.id">
                        <li class="rounded-card border border-line bg-surface p-5 shadow-card">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="break-words font-bold text-ink" x-text="request.customer"></p>
                                    <p class="truncate text-sm text-ink-muted" x-text="request.email"></p>
                                    <p class="mt-1 text-sm text-ink-secondary">
                                        <span x-text="request.program"></span> ·
                                        <span x-text="`${request.stamps} din ${request.required}`"></span>
                                        <span x-show="request.rewards > 0" class="font-semibold text-primary-ink"
                                              x-text="` · ${request.rewards} ${request.rewards === 1 ? 'recompensă' : 'recompense'}`"></span>
                                    </p>
                                </div>
                                <span class="shrink-0 text-xs text-ink-muted" x-text="age(request)"></span>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <template x-for="quantity in quantities(request)" :key="quantity">
                                    <span class="contents">
                                        <template x-if="quantity === 1">
                                            <x-ui.button type="button" size="lg" class="flex-1" @click="approve(request, 1)" ::disabled="acting !== null">Aprobă +1</x-ui.button>
                                        </template>
                                        <template x-if="quantity > 1">
                                            <x-ui.button type="button" variant="secondary" size="lg" @click="approve(request, quantity)" ::disabled="acting !== null" x-text="`+${quantity}`"></x-ui.button>
                                        </template>
                                    </span>
                                </template>
                                <x-ui.button type="button" variant="secondary" size="lg" @click="reject(request)" ::disabled="acting !== null">Respinge</x-ui.button>
                            </div>
                        </li>
                    </template>
                </ul>
            </section>
        @endif
    @endif
</x-app-layout>
