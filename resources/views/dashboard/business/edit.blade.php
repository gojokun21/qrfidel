<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-espresso-900">Setările afacerii</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-10 [&>*]:max-w-2xl">
            <x-flash />

            <x-card>
                <form method="POST" action="{{ route('dashboard.business.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    @include('dashboard.business._fields', ['business' => $business])

                    <div>
                        <x-input-label value="Adresa publică" />
                        <p class="mt-1 font-mono text-sm text-espresso-700">{{ url('/b/'.$business->slug) }}</p>
                    </div>

                    <x-primary-button>Salvează</x-primary-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
