<x-app-layout title="Setările afacerii" width="form">
    <x-ui.card>
        <form method="POST" action="{{ route('dashboard.business.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            @include('dashboard.business._fields', ['business' => $business])

            <div>
                <p class="text-sm font-medium text-ink">Adresa publică</p>
                <p class="mt-1 break-all font-mono text-sm text-ink-secondary">{{ url('/b/'.$business->slug) }}</p>
            </div>

            <x-ui.button>Salvează</x-ui.button>
        </form>
    </x-ui.card>
</x-app-layout>
