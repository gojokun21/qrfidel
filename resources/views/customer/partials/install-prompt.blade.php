{{-- „Adaugă pe ecranul principal” (SDD §5.1): promptul nativ pe Android/Chrome, instrucțiuni pe iOS. --}}
<div x-data="installPrompt" x-show="visible" x-cloak
     class="mb-6 flex items-start gap-3 rounded-2xl border border-gold-200 bg-gold-50 p-4 text-sm text-espresso-900">
    <img src="/icons/icon-192.png" alt="" class="h-10 w-10 shrink-0 rounded-xl">
    <div class="min-w-0 flex-1">
        <p class="font-semibold">Ține cardul la un tap distanță</p>
        <p class="mt-0.5 text-espresso-700" x-show="canPrompt">Adaugă aplicația pe ecranul principal.</p>
        <p class="mt-0.5 text-espresso-700" x-show="!canPrompt && ios">Apasă <strong>Partajează</strong>, apoi <strong>Adaugă pe ecranul principal</strong>.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" x-show="canPrompt" @click="install()"
                    class="rounded-xl bg-espresso-900 px-4 py-2 font-semibold text-cream-50 transition hover:bg-espresso-800">Adaugă pe ecranul principal</button>
            <button type="button" @click="dismiss()" class="rounded-xl px-3 py-2 font-semibold text-espresso-700 transition hover:text-espresso-950">Nu acum</button>
        </div>
    </div>
</div>
