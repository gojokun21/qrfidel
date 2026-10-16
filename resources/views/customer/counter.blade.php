{{--
    QR-ul de pe tejghea (SDD §5.3, ADR 012). Clientul cere ștampila; angajatul o confirmă din panou.
    Fără card, un singur tap înscrie clientul și trimite cererea.
--}}
<x-customer-layout :title="$business->name">
    <div class="flex items-center gap-4">
        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-2xl font-bold"
              style="background-color: {{ $business->brand_color ?? \App\Models\Program::DEFAULT_COLOR }}; color: {{ \App\Support\Color::readableOn($business->brand_color ?? \App\Models\Program::DEFAULT_COLOR) }}">{{ mb_strtoupper(mb_substr($business->name, 0, 1)) }}</span>
        <div class="min-w-0">
            <h1 class="truncate text-2xl font-bold text-espresso-950">{{ $business->name }}</h1>
            <p class="flex items-center gap-1 text-sm text-espresso-600">
                <x-icon name="map-pin" class="h-4 w-4 text-gold-600" />
                {{ $location->name }}
            </p>
        </div>
    </div>

    <div class="mt-6">
        <x-flash />
    </div>

    @if ($programs->isEmpty())
        <div class="rounded-3xl bg-white p-6 text-center shadow-xl shadow-espresso-950/5">
            <p class="font-semibold text-espresso-900">{{ $business->name }} nu are acum un program activ.</p>
        </div>
    @else
        @guest('customer')
            <div class="rounded-3xl bg-white p-6 shadow-xl shadow-espresso-950/5">
                <h2 class="text-lg font-bold text-espresso-950">Cere ștampila de la tejghea</h2>
                <p class="mt-1 text-sm text-espresso-600">Intră în cont cu emailul. Linkul te aduce înapoi aici, ca să trimiți cererea.</p>
                <div class="mt-5">
                    @include('customer.partials.magic-link-form', ['business' => $business, 'counterLocation' => $location])
                </div>
            </div>
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
                    <section class="rounded-3xl bg-white p-5 shadow-xl shadow-espresso-950/5" aria-labelledby="program-{{ $program->id }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h2 id="program-{{ $program->id }}" class="font-bold text-espresso-950">{{ $program->name }}</h2>
                                <p class="mt-1 text-sm text-espresso-600">{{ $program->stamps_required }} ștampile → {{ $program->reward_title }}</p>
                            </div>
                            @if ($card)
                                <p class="shrink-0 rounded-full bg-cream-100 px-3 py-1 text-sm font-semibold text-espresso-900">
                                    {{ $card->stamps_balance }} din {{ $program->stamps_required }}
                                </p>
                            @endif
                        </div>

                        <div class="mt-4">
                            @if ($waitingHere)
                                <p class="rounded-2xl bg-gold-50 px-4 py-3 text-sm font-semibold text-gold-800">Cerere trimisă – angajatul o confirmă imediat.</p>
                                <a href="{{ route('customer.cards.show', $card) }}" class="btn-outline mt-3 w-full">Vezi cardul</a>
                            @else
                                <form method="POST" action="{{ route('customer.counter.request', [$location->counter_token, $program]) }}"
                                      x-data="{ sending: false }" @submit="sending = true">
                                    @csrf
                                    <button class="btn-gold w-full py-4 text-lg" :disabled="sending">
                                        {{ $card ? 'Cere ștampilă' : 'Înscrie-te și cere ștampila' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        @endauth
    @endif

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('customer.business', $business) }}" class="link">Despre {{ $business->name }}</a>
    </p>
</x-customer-layout>
