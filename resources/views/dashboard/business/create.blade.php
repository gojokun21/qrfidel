<x-app-layout title="Creează afacerea" width="form">
    <x-ui.card>
        <form method="POST" action="{{ route('dashboard.business.store') }}" class="space-y-5" x-data="{ sending: false }" @submit="sending = true">
            @csrf
            <x-ui.idempotency-key />

            @include('dashboard.business._fields')

            <x-ui.field label="Adresa publică" for="slug" optional hint="Apare în QR-urile tipărite, așa că nu se mai poate schimba după creare.">
                <div class="flex flex-col gap-1 text-sm text-ink-muted sm:flex-row sm:items-center">
                    <span class="shrink-0">{{ url('/b') }}/</span>
                    <x-ui.input id="slug" name="slug" type="text" :value="old('slug')" placeholder="se generează din nume" maxlength="60" />
                </div>
            </x-ui.field>

            <x-ui.button ::disabled="sending">Creează afacerea</x-ui.button>
        </form>
    </x-ui.card>
</x-app-layout>
