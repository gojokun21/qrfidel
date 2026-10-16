{{-- Pagina „Tranzacții” (manager+, ADR 013): mișcările afacerii, cu anulare pe cele încă anulabile. --}}
@php
    $timezone = $business->timezone;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-espresso-900">Tranzacții</h2>
            <p class="mt-1 text-sm text-espresso-500">Ștampilele și recompensele se pot anula în primele {{ \App\Services\Loyalty\VoidService::WINDOW_HOURS }} de ore; o ștampilă doar dacă e ultima de pe card.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-10">
            <x-flash />

            <form method="GET" action="{{ route('dashboard.transactions.index') }}" class="flex flex-wrap items-end gap-3">
                @if ($locations->isNotEmpty())
                    <div>
                        <x-input-label for="location" value="Locația" />
                        <x-select-input id="location" name="location" class="mt-1">
                            <option value="">Toate locațiile</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected($filters['location'] === $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                @endif
                <div>
                    <x-input-label for="type" value="Tipul" />
                    <x-select-input id="type" name="type" class="mt-1">
                        <option value="">Toate tipurile</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected($filters['type'] === $type)>{{ $type->label() }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <button class="btn-outline">Filtrează</button>
            </form>

            <x-card flush>
                @forelse ($transactions as $transaction)
                    @php
                        $card = $transaction->card;
                    @endphp
                    <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-start sm:justify-between {{ $loop->first ? '' : 'border-t border-espresso-100' }}">
                        <div class="min-w-0">
                            <p class="font-semibold text-espresso-900 {{ $transaction->voided_by_id ? 'line-through' : '' }}">
                                {{ $transaction->type->label() }}@if ($transaction->type === \App\Enums\TransactionType::Stamp) ×{{ $transaction->quantity }}@endif
                                @if ($transaction->voided_by_id)
                                    <span class="ml-1 rounded-full bg-cream-100 px-2 py-0.5 text-xs font-medium text-espresso-600 no-underline">anulată</span>
                                @endif
                            </p>
                            <p class="truncate text-sm text-espresso-600">
                                {{ $card?->customer?->maskedEmail() }} · {{ $card?->program?->name }}
                            </p>
                            <p class="truncate text-xs text-espresso-500">
                                <time datetime="{{ $transaction->created_at->toIso8601String() }}">{{ $transaction->created_at->setTimezone($timezone)->format('d.m.Y H:i') }}</time>
                                @if ($transaction->staff) · {{ $transaction->staff->name }}@endif
                                @if ($transaction->location) · {{ $transaction->location->name }}@endif
                                @if ($transaction->type === \App\Enums\TransactionType::Void && ($transaction->meta['reason'] ?? null))
                                    · Motiv: {{ $transaction->meta['reason'] }}
                                @endif
                            </p>
                        </div>

                        @if (isset($voidable[$transaction->id]))
                            <div class="shrink-0 sm:w-80">
                                @include('dashboard.transactions._void-form', ['transaction' => $transaction])
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="p-6 text-espresso-500">Nicio tranzacție.</p>
                @endforelse
            </x-card>

            {{ $transactions->links() }}
        </div>
    </div>
</x-app-layout>
