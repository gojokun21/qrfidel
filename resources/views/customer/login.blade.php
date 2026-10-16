<x-customer-layout title="Intră în cont">
    <x-ui.card>
        <h1 class="text-xl font-bold text-ink">Cardurile tale de fidelitate</h1>
        <p class="mt-1 text-sm text-ink-secondary">Intră în cont ca să-ți vezi ștampilele și recompensele de la toate localurile.</p>

        <div class="mt-6 space-y-4">
            <x-layout.flash />
            @include('customer.partials.magic-link-form')
        </div>
    </x-ui.card>
</x-customer-layout>
