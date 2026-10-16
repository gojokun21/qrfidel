<x-customer-layout title="Link expirat">
    <div class="rounded-3xl bg-white p-6 text-center shadow-xl shadow-espresso-950/5">
        <h1 class="text-xl font-bold text-espresso-950">Linkul nu mai e valabil</h1>
        <p class="mt-2 text-sm text-espresso-600">{{ $message }}</p>

        <a href="{{ route('customer.login') }}" class="btn-gold mt-6 w-full">Cere un link nou</a>
    </div>
</x-customer-layout>
