<x-app-layout title="Locație nouă" width="form" :back="route('dashboard.locations.index')" back-label="Locații">
    <x-ui.card>
        <form method="POST" action="{{ route('dashboard.locations.store') }}" class="space-y-5">
            @csrf
            @include('dashboard.locations._form')
        </form>
    </x-ui.card>
</x-app-layout>
