<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold tracking-tight text-espresso-900">Locații</h2>
            @can('create', [\App\Models\Location::class, $business])
                <a href="{{ route('dashboard.locations.create') }}" class="btn-gold">Adaugă locație</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-10">
            <x-flash />

            <x-card flush>
                @forelse ($locations as $location)
                    <div class="flex flex-col gap-3 p-6 sm:flex-row sm:items-center sm:justify-between {{ $loop->first ? '' : 'border-t border-espresso-100' }}">
                        <div class="flex min-w-0 items-start gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gold-50 text-gold-600"><x-icon name="map-pin" class="h-5 w-5" /></span>
                            <div class="min-w-0">
                                <p class="font-semibold text-espresso-900">{{ $location->name }}</p>
                                @if ($location->address)
                                    <p class="text-sm text-espresso-500">{{ $location->address }}</p>
                                @endif
                                @can('update', $location)
                                    <p class="mt-1 text-xs text-espresso-400">QR tejghea:
                                        <a href="{{ route('customer.counter', $location->counter_token) }}" target="_blank" rel="noopener"
                                           class="font-mono break-all hover:text-espresso-700 hover:underline">{{ route('customer.counter', $location->counter_token) }}</a>
                                    </p>
                                @endcan
                            </div>
                        </div>

                        @can('update', $location)
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <a href="{{ route('dashboard.locations.edit', $location) }}" class="link">Editează</a>
                                <form method="POST" action="{{ route('dashboard.locations.counter-token', $location) }}"
                                      x-data @submit="if (! confirm('QR-ul tipărit al locației nu va mai funcționa. Continui?')) $event.preventDefault()">
                                    @csrf
                                    <button class="font-medium text-espresso-600 hover:text-espresso-900 hover:underline">Regenerează QR</button>
                                </form>
                                <form method="POST" action="{{ route('dashboard.locations.destroy', $location) }}"
                                      x-data @submit="if (! confirm('Ștergi această locație?')) $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline">Șterge</button>
                                </form>
                            </div>
                        @endcan
                    </div>
                @empty
                    <p class="p-6 text-espresso-500">Nu există încă nicio locație.</p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-app-layout>
