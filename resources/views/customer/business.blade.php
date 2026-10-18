<x-customer-layout :title="$business->name">
    @php($brandColor = \App\Models\Program::fallbackColorFor($business))
    {{-- Antetul afacerii: banda în culoarea brandului (hex validat la salvare), avatarul suprapus. --}}
    <header class="overflow-hidden rounded-[1.75rem] border border-line bg-white shadow-soft">
        <div class="relative h-24" style="background-color: {{ $brandColor }}">
            <div class="absolute inset-0 bg-gradient-to-br from-white/25 to-black/10" aria-hidden="true"></div>
            <div class="absolute inset-0 bg-[radial-gradient(rgb(255_255_255/0.22)_1px,transparent_1px)] bg-[length:16px_16px]" aria-hidden="true"></div>
        </div>
        <div class="px-5 pb-5">
            <x-ui.avatar :name="$business->name" size="lg" :color="$brandColor" class="relative -mt-8 ring-4 ring-white" />
            <h1 class="mt-3 break-words text-2xl font-semibold tracking-tight text-ink">{{ $business->name }}</h1>
            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-ink-secondary">
                <x-ui.icon name="gift" class="h-4 w-4 text-primary-ink" />
                Program de fidelitate
            </p>
        </div>
    </header>

    <x-layout.flash />

    @if ($programs->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="gift" :title="$business->name.' nu are încă un program activ.'">Revino curând.</x-ui.empty-state>
        </x-ui.card>
    @else
        @guest('customer')
            <x-ui.card title="Strânge ștampile, primește recompense" description="Intră în cont cu emailul, apoi înscrie-te în program. Cardul tău stă în telefon.">
                @include('customer.partials.magic-link-form', ['business' => $business])
            </x-ui.card>
        @endguest

        @foreach ($programs as $program)
            @php($card = $cards->get($program->id))
            <x-ui.card flush>
                <section class="p-4 sm:p-5" aria-labelledby="program-{{ $program->id }}">
                    <x-loyalty.card :business="$business->name" :name="$program->name" :required="$program->stamps_required"
                                    :reward="$program->reward_title" :color="$program->displayColor()" :icon="$program->stamp_icon->value"
                                    :filled="$card?->stamps_balance ?? 0" />

                    <div class="mt-4 px-1">
                        <h2 id="program-{{ $program->id }}" class="sr-only">{{ $program->name }}</h2>
                        @if ($program->reward_description)
                            <p class="text-sm text-ink-secondary">{{ $program->reward_description }}</p>
                        @endif
                        <ul @class(['flex flex-wrap gap-2', 'mt-3' => $program->reward_description])>
                            <li><x-ui.badge>Maximum {{ $program->max_stamps_per_visit }} pe vizită</x-ui.badge></li>
                            @if ($program->daily_limit)
                                <li><x-ui.badge>Maximum {{ $program->daily_limit }} pe zi</x-ui.badge></li>
                            @endif
                            @if ($program->reward_expire_days)
                                <li><x-ui.badge>Recompensa expiră în {{ $program->reward_expire_days }} zile</x-ui.badge></li>
                            @endif
                        </ul>
                        @if ($program->terms)
                            <details class="mt-3 text-sm text-ink-secondary">
                                <summary class="cursor-pointer rounded-sm font-medium text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">Termeni</summary>
                                <p class="mt-2 whitespace-pre-line">{{ $program->terms }}</p>
                            </details>
                        @endif

                        @auth('customer')
                            <div class="mt-5">
                                @if ($card)
                                    <p class="flex items-center gap-1.5 text-sm font-semibold text-success">
                                        <x-ui.icon name="shield" class="h-4 w-4" /> Ești înscris în acest program.
                                    </p>
                                    <x-ui.button :href="route('customer.cards')" variant="secondary" class="mt-3 w-full">Vezi cardurile mele</x-ui.button>
                                @else
                                    <form method="POST" action="{{ route('customer.enroll', [$business, $program]) }}" x-data="{ sending: false }" @submit="sending = true">
                                        @csrf
                                        <x-ui.button class="w-full" ::disabled="sending">Înscrie-te</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        @endauth
                    </div>
                </section>
            </x-ui.card>
        @endforeach
    @endif
</x-customer-layout>
