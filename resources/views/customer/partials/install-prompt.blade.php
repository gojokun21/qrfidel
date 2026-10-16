{{-- „Adaugă pe ecranul principal” (SDD §5.1): promptul nativ pe Android/Chrome, instrucțiuni pe iOS. --}}
<div x-data="installPrompt" x-show="visible" x-cloak
     class="flex items-start gap-3 rounded-card border border-primary-line bg-primary-soft p-4 text-sm text-ink">
    <img src="/icons/icon-192.png" alt="" class="h-10 w-10 shrink-0 rounded-control">
    <div class="min-w-0 flex-1">
        <p class="font-semibold">Ține cardul la un tap distanță</p>
        <p class="mt-0.5 text-ink-secondary" x-show="canPrompt">Adaugă aplicația pe ecranul principal.</p>
        <p class="mt-0.5 text-ink-secondary" x-show="!canPrompt && ios">Apasă <strong>Partajează</strong>, apoi <strong>Adaugă pe ecranul principal</strong>.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <x-ui.button type="button" variant="neutral" size="sm" x-show="canPrompt" @click="install()">Adaugă pe ecranul principal</x-ui.button>
            <x-ui.button type="button" variant="ghost" size="sm" @click="dismiss()">Nu acum</x-ui.button>
        </div>
    </div>
</div>
