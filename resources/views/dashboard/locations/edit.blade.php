<x-app-layout title="Editează locația" width="form" :back="route('dashboard.locations.index')" back-label="Locații">
    <x-ui.card>
        <form method="POST" action="{{ route('dashboard.locations.update', $location) }}" class="space-y-5" x-data="{ sending: false }" @submit="sending = true">
            @csrf
            @method('PATCH')
            @include('dashboard.locations._form')
        </form>
    </x-ui.card>
</x-app-layout>
