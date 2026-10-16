<x-customer-layout title="Intră în cont">
    <div class="rounded-3xl bg-white p-6 text-center shadow-xl shadow-espresso-950/5">
        <x-flash />
        <h1 class="text-xl font-bold text-espresso-950">Intră în cont</h1>
        <p class="mt-2 text-sm text-espresso-600">
            Te conectezi ca <strong class="break-all text-espresso-900">{{ $email }}</strong>. Rămâi conectat pe acest dispozitiv.
        </p>

        <form method="POST" action="{{ $action }}" class="mt-6" x-data="{ sending: false }" @submit="sending = true">
            @csrf
            <button class="btn-gold w-full" :disabled="sending">Continuă</button>
        </form>
    </div>
</x-customer-layout>
