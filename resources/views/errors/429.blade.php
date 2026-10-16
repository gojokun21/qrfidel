{{-- Pagina 429 în română (ADR 013); mesajul vine din handler-ul din bootstrap/app.php. --}}
<x-guest-layout>
    <div class="text-center">
        <h1 class="text-xl font-bold text-espresso-950">Prea multe încercări</h1>
        <p class="mt-2 text-sm text-espresso-600">{{ $message ?? 'Prea multe încercări. Încearcă din nou peste un minut.' }}</p>

        <a href="{{ url()->previous() }}" class="btn-gold mt-6 w-full">Înapoi</a>
    </div>
</x-guest-layout>
