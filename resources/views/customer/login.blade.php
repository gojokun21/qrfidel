<x-customer-layout title="Intră în cont">
    <div class="rounded-3xl bg-white p-6 shadow-xl shadow-espresso-950/5">
        <h1 class="text-xl font-bold text-espresso-950">Cardurile tale de fidelitate</h1>
        <p class="mt-1 text-sm text-espresso-600">Intră în cont ca să-ți vezi ștampilele și recompensele de la toate localurile.</p>

        <div class="mt-6">
            <x-flash />
            @include('customer.partials.magic-link-form')
        </div>
    </div>
</x-customer-layout>
