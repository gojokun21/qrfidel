{{--
    QR-ul de pe tejghea (SDD §5.3, ADR 012). Clientul cere ștampila; angajatul o confirmă din panou.
    Fără card, un singur tap înscrie clientul și trimite cererea.
--}}
<x-customer-layout :title="$business->name">
    <div class="flex items-center gap-4">
        <x-ui.avatar :name="$business->name" size="lg" :color="$business->brand_color ?? \App\Models\Program::DEFAULT_COLOR" />
        <div class="min-w-0">
            <h1 class="break-words text-2xl font-bold text-ink">{{ $business->name }}</h1>
            <p class="flex items-center gap-1 text-sm text-ink-secondary">
                <x-ui.icon name="map-pin" class="h-4 w-4 text-primary-ink" />
                {{ $location->name }}
            </p>
        </div>
    </div>

    <x-layout.flash />

    @if ($programs->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="gift" :title="$business->name.' nu are acum un program activ.'" />
        </x-ui.card>
    @else
        @guest('customer')
            <x-ui.card title="Cere ștampila de la tejghea" description="Intră în cont cu emailul. Linkul te aduce înapoi aici, ca să trimiți cererea.">
                @include('customer.partials.magic-link-form', ['business' => $business, 'counterLocation' => $location])
            </x-ui.card>
        @endguest

        @auth('customer')
            <div class="space-y-4">
                @foreach ($programs as $program)
                    @php
                        $card = $cards->get($program->id);
                        $stampRequest = $card ? $requests->get($card->id) : null;
                        // O cerere care așteaptă la altă tejghea se poate muta aici cu butonul.
                        $waitingHere = $stampRequest?->isOpen() && $stampRequest->location_id === $location->id;
                    @endphp
                    <x-ui.card>
                        <section aria-labelledby="program-{{ $program->id }}">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <h2 id="program-{{ $program->id }}" class="font-bold text-ink">{{ $program->name }}</h2>
                                    <p class="mt-1 text-sm text-ink-secondary">{{ $program->stamps_required }} ștampile → {{ $program->reward_title }}</p>
                                </div>
                                @if ($card)
                                    <x-ui.badge class="text-sm">{{ $card->stamps_balance }} din {{ $program->stamps_required }}</x-ui.badge>
                                @endif
                            </div>

                            <div class="mt-4">
                                @if ($waitingHere)
                                    <x-loyalty.request-pending class="font-semibold">Cerere trimisă – angajatul o confirmă imediat.</x-loyalty.request-pending>
                                    <x-ui.button :href="route('customer.cards.show', $card)" variant="secondary" class="mt-3 w-full">Vezi cardul</x-ui.button>
                                @else
                                    <form method="POST" action="{{ route('customer.counter.request', [$location->counter_token, $program]) }}"
                                          x-data="{ sending: false }" @submit="sending = true">
                                        @csrf
                                        <x-ui.button size="xl" class="w-full" ::disabled="sending">
                                            {{ $card ? 'Cere ștampilă' : 'Înscrie-te și cere ștampila' }}
                                        </x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </section>
                    </x-ui.card>
                @endforeach
            </div>
        @endauth
    @endif

    <p class="text-center text-sm">
        <x-ui.link :href="route('customer.business', $business)">Despre {{ $business->name }}</x-ui.link>
    </p>
</x-customer-layout>
