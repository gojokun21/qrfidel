<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Locații</h2>
            @can('create', [\App\Models\Location::class, $business])
                <a href="{{ route('dashboard.locations.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-gray-700">Adaugă locație</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-flash />

            <x-card class="p-0 overflow-hidden">
                @forelse ($locations as $location)
                    <div class="flex flex-col gap-3 p-6 sm:flex-row sm:items-center sm:justify-between {{ $loop->first ? '' : 'border-t border-gray-100' }}">
                        <div>
                            <p class="font-medium text-gray-900">{{ $location->name }}</p>
                            @if ($location->address)
                                <p class="text-sm text-gray-500">{{ $location->address }}</p>
                            @endif
                            @can('update', $location)
                                <p class="mt-1 text-xs text-gray-400">QR tejghea: <span class="font-mono break-all">{{ url('/l/'.$location->counter_token) }}</span></p>
                            @endcan
                        </div>

                        @can('update', $location)
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <a href="{{ route('dashboard.locations.edit', $location) }}" class="text-indigo-600 hover:underline">Editează</a>
                                <form method="POST" action="{{ route('dashboard.locations.counter-token', $location) }}"
                                      x-data @submit="if (! confirm('QR-ul tipărit al locației nu va mai funcționa. Continui?')) $event.preventDefault()">
                                    @csrf
                                    <button class="text-gray-600 hover:underline">Regenerează QR</button>
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
                    <p class="p-6 text-gray-500">Nu există încă nicio locație.</p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-app-layout>
