<x-customer-layout :title="$business->name">
    <div class="flex items-center gap-4">
        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-2xl font-bold"
              style="background-color: {{ $business->brand_color ?? \App\Models\Program::DEFAULT_COLOR }}; color: {{ \App\Support\Color::readableOn($business->brand_color ?? \App\Models\Program::DEFAULT_COLOR) }}">{{ mb_strtoupper(mb_substr($business->name, 0, 1)) }}</span>
        <div class="min-w-0">
            <h1 class="truncate text-2xl font-bold text-espresso-950">{{ $business->name }}</h1>
            <p class="text-sm text-espresso-600">Program de fidelitate</p>
        </div>
    </div>

    <div class="mt-6">
        <x-flash />
    </div>

    @if ($programs->isEmpty())
        <div class="rounded-3xl bg-white p-6 text-center shadow-xl shadow-espresso-950/5">
            <p class="font-semibold text-espresso-900">{{ $business->name }} nu are încă un program activ.</p>
            <p class="mt-1 text-sm text-espresso-600">Revino curând.</p>
        </div>
    @else
        @guest('customer')
            <div class="mb-6 rounded-3xl bg-white p-6 shadow-xl shadow-espresso-950/5">
                <h2 class="text-lg font-bold text-espresso-950">Strânge ștampile, primește recompense</h2>
                <p class="mt-1 text-sm text-espresso-600">Intră în cont cu emailul, apoi înscrie-te în program. Cardul tău stă în telefon.</p>
                <div class="mt-5">
                    @include('customer.partials.magic-link-form', ['business' => $business])
                </div>
            </div>
        @endguest

        <div class="space-y-6">
            @foreach ($programs as $program)
                @php($card = $cards->get($program->id))
                <section class="rounded-3xl bg-white p-4 shadow-xl shadow-espresso-950/5 sm:p-5" aria-labelledby="program-{{ $program->id }}">
                    <x-loyalty-card :business="$business->name" :name="$program->name" :required="$program->stamps_required"
                                    :reward="$program->reward_title" :color="$program->displayColor()" :icon="$program->stamp_icon->value"
                                    :filled="$card?->stamps_balance ?? 0" />

                    <div class="mt-4 px-1">
                        <h2 id="program-{{ $program->id }}" class="font-bold text-espresso-950">{{ $program->name }}</h2>
                        <p class="mt-1 text-sm text-espresso-700">
                            {{ $program->stamps_required }} ștampile → <strong>{{ $program->reward_title }}</strong>
                        </p>
                        @if ($program->reward_description)
                            <p class="mt-1 text-sm text-espresso-600">{{ $program->reward_description }}</p>
                        @endif
                        <ul class="mt-3 flex flex-wrap gap-2 text-xs text-espresso-600">
                            <li class="rounded-full bg-cream-100 px-3 py-1">Maximum {{ $program->max_stamps_per_visit }} pe vizită</li>
                            @if ($program->daily_limit)
                                <li class="rounded-full bg-cream-100 px-3 py-1">Maximum {{ $program->daily_limit }} pe zi</li>
                            @endif
                            @if ($program->reward_expire_days)
                                <li class="rounded-full bg-cream-100 px-3 py-1">Recompensa expiră în {{ $program->reward_expire_days }} zile</li>
                            @endif
                        </ul>
                        @if ($program->terms)
                            <details class="mt-3 text-sm text-espresso-600">
                                <summary class="cursor-pointer font-medium text-espresso-800">Termeni</summary>
                                <p class="mt-2 whitespace-pre-line">{{ $program->terms }}</p>
                            </details>
                        @endif

                        @auth('customer')
                            <div class="mt-5">
                                @if ($card)
                                    <p class="text-sm font-semibold text-emerald-700">Ești înscris în acest program.</p>
                                    <a href="{{ route('customer.cards') }}" class="btn-outline mt-3 w-full">Vezi cardurile mele</a>
                                @else
                                    <form method="POST" action="{{ route('customer.enroll', [$business, $program]) }}" x-data="{ sending: false }" @submit="sending = true">
                                        @csrf
                                        <button class="btn-gold w-full" :disabled="sending">Înscrie-te</button>
                                    </form>
                                @endif
                            </div>
                        @endauth
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-customer-layout>
