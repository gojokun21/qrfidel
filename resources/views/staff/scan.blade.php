<x-app-layout title="Scanează cardul" width="narrow">
    @if ($locations->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="map-pin" title="Adaugă întâi o locație">
                Fiecare ștampilă se înregistrează pe o locație. Owner-ul o poate adăuga din pagina Locații.
            </x-ui.empty-state>
        </x-ui.card>
    @else
        @include('staff.partials.location-picker')

        @if ($location)
            <section x-data="qrScanner($el.dataset.url)" data-url="{{ route('staff.scan.resolve') }}" aria-label="Camera">
                <div class="relative aspect-square w-full overflow-hidden rounded-panel bg-inverse-deep">
                    <video x-ref="video" class="h-full w-full object-cover" muted playsinline></video>
                    <p x-show="state === 'starting'" class="absolute inset-0 flex items-center justify-center p-8 text-center text-sm text-inverse-muted">Pornim camera… Dacă browserul cere acces la cameră, permite-l.</p>
                    <p x-show="state === 'busy'" x-cloak class="absolute inset-0 flex items-center justify-center bg-inverse-deep/70 text-sm font-semibold text-inverse-fg" role="status">Verificăm codul…</p>
                    <div x-show="state === 'unavailable'" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-8 text-center text-inverse-muted">
                        <x-ui.icon name="qr" class="h-10 w-10 text-accent" />
                        <p class="text-sm" x-text="error"></p>
                    </div>
                </div>
                <x-ui.alert tone="danger" x-show="error && state !== 'unavailable'" x-cloak class="mt-3"><span x-text="error"></span></x-ui.alert>
            </section>

            <form method="POST" action="{{ route('staff.scan.resolve') }}" x-data
                  @submit="setTimeout(() => $el.querySelector('button').disabled = true)">
                @csrf
                <x-ui.field label="Sau tastează codul dictat de client" for="code">
                    <div class="flex gap-3">
                        <x-ui.input id="code" name="code" class="font-mono text-lg uppercase tracking-eyebrow"
                                    autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="255"
                                    placeholder="ABC DEF" required />
                        <x-ui.button class="shrink-0">Verifică</x-ui.button>
                    </div>
                </x-ui.field>
            </form>
        @endif
    @endif
</x-app-layout>
