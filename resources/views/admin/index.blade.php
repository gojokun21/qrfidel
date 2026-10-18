{{-- Panoul adminului de platformă (ADR 014): totaluri și lista afacerilor. --}}
<x-app-layout title="Admin platformă">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-ui.stat label="Afaceri active" :value="$totals['businesses_active']" />
        <x-ui.stat label="Afaceri suspendate" :value="$totals['businesses_suspended']" />
        <x-ui.stat label="Clienți" :value="$totals['customers']" />
        <x-ui.stat label="Carduri" :value="$totals['cards']" />
        <x-ui.stat label="Ștampile (30 de zile)" :value="$totals['stamps_30_days']" />
    </div>

    <form method="GET" action="{{ route('admin.index') }}" class="flex flex-wrap items-end gap-3" role="search">
        <x-ui.field label="Caută" for="q" class="min-w-0 flex-1 sm:max-w-72">
            <x-ui.input id="q" name="q" type="search" :value="$filters['q']" placeholder="Nume sau slug" />
        </x-ui.field>
        <x-ui.field label="Statusul" for="status">
            <x-ui.select id="status" name="status">
                <option value="">Toate</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status)>{{ $status->label() }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.button variant="secondary">Filtrează</x-ui.button>
    </form>

    <x-ui.card flush>
        @if ($businesses->isEmpty())
            <x-ui.empty-state icon="building" title="Nicio afacere." />
        @else
            <ul class="divide-y divide-line">
                @foreach ($businesses as $business)
                    <li>
                        <a href="{{ route('admin.businesses.show', $business) }}"
                           class="flex flex-col gap-3 p-5 transition hover:bg-canvas focus:outline-none focus-visible:bg-canvas focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-focus sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 font-semibold text-ink">
                                    {{ $business->name }}
                                    <x-loyalty.business-status :status="$business->status" />
                                </p>
                                <p class="break-words text-sm text-ink-secondary">
                                    <span class="text-xs">/b/{{ $business->slug }}</span> · {{ $business->type->label() }}
                                    @if ($business->members->isNotEmpty())
                                        · {{ $business->members->map(fn ($owner) => $owner->email)->join(', ') }}
                                    @endif
                                </p>
                            </div>
                            <dl class="flex shrink-0 gap-5 text-sm">
                                <div><dt class="text-xs text-ink-muted">Locații</dt><dd class="font-semibold tabular-nums text-ink">{{ $business->locations_count }}</dd></div>
                                <div><dt class="text-xs text-ink-muted">Carduri</dt><dd class="font-semibold tabular-nums text-ink">{{ $business->cards_count }}</dd></div>
                                <div><dt class="text-xs text-ink-muted">Ștampile 30 z</dt><dd class="font-semibold tabular-nums text-ink">{{ (int) $business->stamps_30_days }}</dd></div>
                            </dl>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{ $businesses->links() }}
</x-app-layout>
