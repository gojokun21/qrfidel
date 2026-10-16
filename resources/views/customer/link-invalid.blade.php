<x-customer-layout title="Link expirat">
    <x-ui.card class="text-center">
        <h1 class="text-xl font-bold text-ink">Linkul nu mai e valabil</h1>
        <p class="mt-2 text-sm text-ink-secondary">{{ $message }}</p>

        <x-ui.button :href="route('customer.login')" class="mt-6 w-full">Cere un link nou</x-ui.button>
    </x-ui.card>
</x-customer-layout>
