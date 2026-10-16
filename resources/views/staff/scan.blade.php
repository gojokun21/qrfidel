<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-espresso-900">Scanează cardul</h2>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-lg space-y-6 px-4 sm:px-6">
            <x-flash />

            @if ($locations->isEmpty())
                <x-card>
                    <p class="font-semibold text-espresso-900">Adaugă întâi o locație</p>
                    <p class="mt-1 text-sm text-espresso-600">Fiecare ștampilă se înregistrează pe o locație. Owner-ul o poate adăuga din pagina Locații.</p>
                </x-card>
            @else
                @include('staff.partials.location-picker')

                @if ($location)
                    <section x-data="qrScanner($el.dataset.url)" data-url="{{ route('staff.scan.resolve') }}" aria-label="Camera">
                        <div class="relative aspect-square w-full overflow-hidden rounded-3xl bg-espresso-950">
                            <video x-ref="video" class="h-full w-full object-cover" muted playsinline></video>
                            <p x-show="state === 'starting'" class="absolute inset-0 flex items-center justify-center p-8 text-center text-sm text-espresso-200">Pornim camera… Dacă browserul cere acces la cameră, permite-l.</p>
                            <p x-show="state === 'busy'" x-cloak class="absolute inset-0 flex items-center justify-center bg-espresso-950/70 text-sm font-semibold text-cream-50">Verificăm codul…</p>
                            <div x-show="state === 'unavailable'" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-8 text-center text-espresso-200">
                                <x-icon name="qr" class="h-10 w-10 text-gold-500" />
                                <p class="text-sm" x-text="error"></p>
                            </div>
                        </div>
                        <p x-show="error && state !== 'unavailable'" x-cloak x-text="error" role="alert"
                           class="mt-3 rounded-2xl border border-red-200 bg-red-50 p-3 text-sm text-red-900"></p>
                    </section>

                    <form method="POST" action="{{ route('staff.scan.resolve') }}" x-data
                          @submit="setTimeout(() => $el.querySelector('button').disabled = true)">
                        @csrf
                        <x-input-label for="code" value="Sau tastează codul dictat de client" />
                        <div class="mt-1 flex gap-3">
                            <x-text-input id="code" name="code" class="block w-full font-mono text-lg uppercase tracking-[0.2em]"
                                          autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="255"
                                          placeholder="ABC DEF" required />
                            <button class="btn-gold shrink-0">Verifică</button>
                        </div>
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </form>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
