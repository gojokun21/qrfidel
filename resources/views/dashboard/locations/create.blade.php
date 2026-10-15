<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Locație nouă</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-card>
                <form method="POST" action="{{ route('dashboard.locations.store') }}" class="space-y-6">
                    @csrf
                    @include('dashboard.locations._form')
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
