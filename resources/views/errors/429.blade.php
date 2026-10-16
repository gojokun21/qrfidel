{{-- Pagina 429 în română (ADR 013); mesajul vine din handler-ul din bootstrap/app.php. --}}
<x-guest-layout title="Prea multe încercări">
    <div class="text-center">
        <h1 class="text-xl font-bold text-ink">Prea multe încercări</h1>
        <p class="mt-2 text-sm text-ink-secondary">{{ $message ?? 'Prea multe încercări. Încearcă din nou peste un minut.' }}</p>

        <x-ui.button :href="url()->previous()" class="mt-6 w-full">Înapoi</x-ui.button>
    </div>
</x-guest-layout>
