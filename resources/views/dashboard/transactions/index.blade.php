{{-- Pagina „Tranzacții” (manager+, ADR 013): mișcările afacerii, cu anulare pe cele încă anulabile. --}}
@php
    $timezone = $business->timezone;
@endphp

<x-app-layout title="Tranzacții"
              :description="'Ștampilele și recompensele se pot anula în primele '.\App\Services\Loyalty\VoidService::WINDOW_HOURS.' de ore; o ștampilă doar dacă e ultima de pe card.'">
    <form method="GET" action="{{ route('dashboard.transactions.index') }}" class="flex flex-wrap items-end gap-3" aria-label="Filtre">
        @if ($locations->isNotEmpty())
            <x-ui.field label="Locația" for="location">
                <x-ui.select id="location" name="location">
                    <option value="">Toate locațiile</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected($filters['location'] === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
        @endif
        <x-ui.field label="Tipul" for="type">
            <x-ui.select id="type" name="type">
                <option value="">Toate tipurile</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected($filters['type'] === $type)>{{ $type->label() }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.button variant="secondary">Filtrează</x-ui.button>
    </form>

    <x-ui.card flush>
        @if ($transactions->isEmpty())
            <x-ui.empty-state icon="clock" title="Nicio tranzacție">
                Ștampilele, recompensele folosite și anulările apar aici pe măsură ce echipa le face.
            </x-ui.empty-state>
        @else
            <ul class="divide-y divide-line">
                @foreach ($transactions as $transaction)
                    @php
                        $card = $transaction->card;
                    @endphp
                    <li class="flex flex-col gap-3 p-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 font-semibold text-ink">
                                <span @class(['line-through decoration-ink-muted' => $transaction->voided_by_id])>
                                    {{ $transaction->type->label() }}@if ($transaction->type === \App\Enums\TransactionType::Stamp) ×{{ $transaction->quantity }}@endif
                                </span>
                                @if ($transaction->voided_by_id)
                                    <x-ui.badge tone="muted">anulată</x-ui.badge>
                                @endif
                            </p>
                            <p class="break-words text-sm text-ink-secondary">
                                {{ $card?->customer?->maskedEmail() }} · {{ $card?->program?->name }}
                            </p>
                            <p class="break-words text-xs text-ink-muted">
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
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{ $transactions->links() }}
</x-app-layout>
