<x-customer-layout title="Cardurile mele">
    <h1 class="text-2xl font-bold text-espresso-950">Cardurile mele</h1>
    <p class="mt-1 truncate text-sm text-espresso-600">{{ $customer->email }}</p>

    <div class="mt-6">
        <x-flash />
    </div>

    @if ($cards->isEmpty())
        <div class="rounded-3xl bg-white p-6 text-center shadow-xl shadow-espresso-950/5">
            <p class="font-semibold text-espresso-900">Nu ai încă niciun card.</p>
            <p class="mt-1 text-sm text-espresso-600">Scanează QR-ul de la casă sau deschide linkul localului ca să te înscrii.</p>
        </div>
    @else
        <ul class="space-y-6">
            @foreach ($cards as $card)
                @php($program = $card->program)
                <li>
                    <x-loyalty-card :business="$program->business->name" :name="$program->name" :required="$program->stamps_required"
                                    :reward="$program->reward_title" :color="$program->displayColor()" :icon="$program->stamp_icon->value"
                                    :filled="$card->stamps_balance" />
                    <div class="mt-2 flex items-center justify-between gap-3 px-1 text-sm">
                        <a href="{{ route('customer.business', $program->business) }}" class="link truncate">{{ $program->business->name }}</a>
                        @if ($card->rewards_available > 0)
                            <span class="shrink-0 rounded-full bg-gold-100 px-3 py-1 font-semibold text-gold-800">
                                {{ $card->rewards_available === 1 ? '1 recompensă disponibilă' : $card->rewards_available.' recompense disponibile' }}
                            </span>
                        @elseif (! $program->isActive())
                            <span class="shrink-0 text-espresso-500">Program oprit</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-customer-layout>
