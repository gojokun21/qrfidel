<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $business->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <x-card>
                <p class="text-gray-700">Rolul tău: <strong>{{ $role?->label() }}</strong></p>
                <p class="mt-1 text-sm text-gray-500">Pagina publică: <span class="font-mono">{{ url('/b/'.$business->slug) }}</span></p>
            </x-card>

            <div class="grid gap-6 sm:grid-cols-3">
                <x-card>
                    <p class="text-sm text-gray-500">Locații</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $locationsCount }}</p>
                    @if ($role?->atLeast(\App\Enums\Role::Manager))
                        <a href="{{ route('dashboard.locations.index') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">Vezi locațiile</a>
                    @endif
                </x-card>
                <x-card>
                    <p class="text-sm text-gray-500">Membri în echipă</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $membersCount }}</p>
                    @can('manageTeam', $business)
                        <a href="{{ route('dashboard.team.index') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">Gestionează echipa</a>
                    @endcan
                </x-card>
                <x-card>
                    <p class="text-sm text-gray-500">Invitații în așteptare</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $pendingInvitationsCount }}</p>
                </x-card>
            </div>

            @can('update', $business)
                @if ($locationsCount === 0)
                    <x-card>
                        <p class="text-gray-700">Începe prin a adăuga prima locație, apoi invită-ți echipa.</p>
                        <a href="{{ route('dashboard.locations.create') }}" class="mt-3 inline-flex items-center px-4 py-2 bg-gray-800 rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-gray-700">Adaugă o locație</a>
                    </x-card>
                @endif
            @endcan
        </div>
    </div>
</x-app-layout>
