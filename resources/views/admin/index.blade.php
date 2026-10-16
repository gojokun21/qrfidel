{{-- Panoul adminului de platformă (ADR 014): totaluri și lista afacerilor. --}}
@php
    $tiles = [
        ['Afaceri active', $totals['businesses_active'], 'building'],
        ['Afaceri suspendate', $totals['businesses_suspended'], 'shield'],
        ['Clienți', $totals['customers'], 'users'],
        ['Carduri', $totals['cards'], 'gift'],
        ['Ștampile (30 de zile)', $totals['stamps_30_days'], 'chart'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-espresso-900">Admin platformă</h2>
            <p class="mt-1 text-sm text-espresso-500">Toate afacerile de pe platformă. Ștampilele anulate nu se numără.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-10">
            <x-flash />

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($tiles as [$label, $value, $icon])
                    <x-card class="flex flex-col">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-espresso-500">{{ $label }}</p>
                            <x-icon :name="$icon" class="h-5 w-5 shrink-0 text-gold-600" />
                        </div>
                        <p class="mt-3 text-3xl font-bold tracking-tight text-espresso-900">{{ $value }}</p>
                    </x-card>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-0 flex-1 sm:flex-none">
                    <x-input-label for="q" value="Caută" />
                    <x-text-input id="q" name="q" type="search" class="mt-1 block w-full sm:w-72" :value="$filters['q']" placeholder="Nume sau slug" />
                </div>
                <div>
                    <x-input-label for="status" value="Statusul" />
                    <x-select-input id="status" name="status" class="mt-1">
                        <option value="">Toate</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status)>{{ $status->label() }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <button class="btn-outline">Filtrează</button>
            </form>

            <x-card flush>
                @forelse ($businesses as $business)
                    <a href="{{ route('admin.businesses.show', $business) }}"
                       class="flex flex-col gap-2 p-5 hover:bg-cream-50 sm:flex-row sm:items-center sm:justify-between {{ $loop->first ? '' : 'border-t border-espresso-100' }}">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 font-semibold text-espresso-900">
                                {{ $business->name }}
                                @include('admin._status', ['status' => $business->status])
                            </p>
                            <p class="truncate text-sm text-espresso-600">
                                <span class="font-mono text-xs">/b/{{ $business->slug }}</span> · {{ $business->type->label() }}
                                @if ($business->members->isNotEmpty())
                                    · {{ $business->members->map(fn ($owner) => $owner->email)->join(', ') }}
                                @endif
                            </p>
                        </div>
                        <dl class="flex shrink-0 gap-5 text-sm text-espresso-600">
                            <div><dt class="text-xs text-espresso-500">Locații</dt><dd class="font-semibold tabular-nums text-espresso-900">{{ $business->locations_count }}</dd></div>
                            <div><dt class="text-xs text-espresso-500">Carduri</dt><dd class="font-semibold tabular-nums text-espresso-900">{{ $business->cards_count }}</dd></div>
                            <div><dt class="text-xs text-espresso-500">Ștampile 30 z</dt><dd class="font-semibold tabular-nums text-espresso-900">{{ (int) $business->stamps_30_days }}</dd></div>
                        </dl>
                    </a>
                @empty
                    <p class="p-6 text-espresso-500">Nicio afacere.</p>
                @endforelse
            </x-card>

            {{ $businesses->links() }}
        </div>
    </div>
</x-app-layout>
