<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Creează afacerea</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-card>
                <p class="text-gray-600">Vei fi ownerul afacerii. După creare poți adăuga locații și îți poți invita echipa.</p>

                <form method="POST" action="{{ route('dashboard.business.store') }}" class="mt-6 space-y-6">
                    @csrf

                    @include('dashboard.business._fields')

                    <div>
                        <x-input-label for="slug" value="Adresa publică (opțional)" />
                        <div class="mt-1 flex items-center gap-1 text-sm text-gray-500">
                            <span>{{ url('/b') }}/</span>
                            <x-text-input id="slug" name="slug" type="text" class="block w-full font-mono" :value="old('slug')" placeholder="se generează din nume" maxlength="60" />
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Apare în QR-urile tipărite, așa că nu se mai poate schimba după creare.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('slug')" />
                    </div>

                    <x-primary-button>Creează afacerea</x-primary-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
