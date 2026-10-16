<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-espresso-900">Locație nouă</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-10 [&>*]:max-w-2xl">
            <x-card>
                <form method="POST" action="{{ route('dashboard.locations.store') }}" class="space-y-6">
                    @csrf
                    @include('dashboard.locations._form')
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
