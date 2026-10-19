<x-customer-layout title="Cardurile mele">
    <div>
        <h1 class="text-2xl font-bold text-ink">Cardurile mele</h1>
        <p class="mt-1 break-words text-sm text-ink-secondary">{{ $customer->email }}</p>
    </div>

    <x-layout.flash />
    @include('customer.partials.install-prompt')

    @if ($cards->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="gift" title="Nu ai încă niciun card.">
                Scanează QR-ul de la casă sau deschide linkul localului ca să te înscrii.
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <ul class="space-y-6">
            @foreach ($cards as $card)
                @php($program = $card->program)
                <li>
                    <a href="{{ route('customer.cards.show', $card) }}" aria-label="Cardul {{ $program->name }} de la {{ $program->business->name }}: {{ $card->stamps_balance }} din {{ $program->stamps_required }} ștampile"
                       class="block rounded-panel transition hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2">
                        <x-loyalty.card :business="$program->business->name" :name="$program->name" :required="$program->stamps_required"
                                        :reward="$program->reward_title" :color="$program->displayColor()" :icon="$program->stamp_icon->value"
                                        :filled="$card->stamps_balance" />
                    </a>
                    <div class="mt-2 flex items-center justify-between gap-3 px-1 text-sm">
                        <x-ui.link :href="route('customer.business', $program->business)" class="truncate">{{ $program->business->name }}</x-ui.link>
                        @if ($card->rewards_available > 0)
                            <x-ui.badge tone="accent">
                                {{ $card->rewards_available === 1 ? '1 recompensă disponibilă' : $card->rewards_available.' recompense disponibile' }}
                            </x-ui.badge>
                        @elseif (! $program->isActive())
                            <x-ui.badge tone="muted">Program oprit</x-ui.badge>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-customer-layout>
