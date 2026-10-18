<x-customer-layout title="Intră în cont">
    <x-ui.card class="text-center">
        <x-layout.flash class="mb-4 text-left" />
        <h1 class="text-xl font-bold text-ink">Intră în cont</h1>
        <p class="mt-2 text-sm text-ink-secondary">
            Te conectezi ca <strong class="break-words text-ink">{{ $email }}</strong>. Rămâi conectat pe acest dispozitiv.
        </p>

        <form method="POST" action="{{ $action }}" class="mt-6" x-data="{ sending: false }" @submit="sending = true">
            @csrf
            <x-ui.button class="w-full" ::disabled="sending">Continuă</x-ui.button>
        </form>
    </x-ui.card>
</x-customer-layout>
