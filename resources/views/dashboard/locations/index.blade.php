<x-app-layout title="Locații">
    @can('create', [\App\Models\Location::class, $business])
        <x-slot:actions>
            <x-ui.button :href="route('dashboard.locations.create')" icon="plus">Adaugă locație</x-ui.button>
        </x-slot:actions>
    @endcan

    <x-ui.card flush>
        @if ($locations->isEmpty())
            <x-ui.empty-state icon="map-pin" title="Nu există încă nicio locație.">
                Fiecare ștampilă se înregistrează pe o locație, iar fiecare locație are QR-ul ei de tejghea.
            </x-ui.empty-state>
        @else
            <ul class="divide-y divide-line">
                @foreach ($locations as $location)
                    <li class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-start gap-4">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-primary-soft text-primary-ink" aria-hidden="true"><x-ui.icon name="map-pin" class="h-5 w-5" /></span>
                            <div class="min-w-0">
                                <p class="font-semibold text-ink">{{ $location->name }}</p>
                                @if ($location->address)
                                    <p class="text-sm text-ink-muted">{{ $location->address }}</p>
                                @endif
                                @can('update', $location)
                                    <p class="mt-1 text-xs text-ink-muted">QR tejghea:
                                        <a href="{{ route('customer.counter', $location->counter_token) }}" target="_blank" rel="noopener"
                                           class="break-all font-mono hover:text-ink hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">{{ route('customer.counter', $location->counter_token) }}</a>
                                    </p>
                                @endcan
                            </div>
                        </div>

                        @can('update', $location)
                            <div class="-ml-2.5 flex flex-wrap items-center gap-1 sm:ml-0">
                                <x-ui.button :href="route('dashboard.locations.edit', $location)" variant="ghost" size="sm">Editează</x-ui.button>
                                <form method="POST" action="{{ route('dashboard.locations.counter-token', $location) }}"
                                      x-data @submit="if (! confirm('QR-ul tipărit al locației nu va mai funcționa. Continui?')) $event.preventDefault()">
                                    @csrf
                                    <x-ui.button variant="ghost" size="sm">Regenerează QR</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('dashboard.locations.destroy', $location) }}"
                                      x-data @submit="if (! confirm('Ștergi această locație?')) $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button variant="danger-ghost" size="sm">Șterge</x-ui.button>
                                </form>
                            </div>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</x-app-layout>
