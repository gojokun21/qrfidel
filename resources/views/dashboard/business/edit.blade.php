<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Setările afacerii</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-flash />

            <x-card>
                <form method="POST" action="{{ route('dashboard.business.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    @include('dashboard.business._fields', ['business' => $business])

                    <div>
                        <x-input-label value="Adresa publică" />
                        <p class="mt-1 font-mono text-sm text-gray-700">{{ url('/b/'.$business->slug) }}</p>
                    </div>

                    <x-primary-button>Salvează</x-primary-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
