{{--
    Anularea unei tranzacții (SDD §5.6, ADR 013), pe pagina „Tranzacții” și pe ecranul cardului.
    „Anulează” deschide motivul (opțional) și confirmarea; cheia de idempotency e generată la randare,
    deci un dublu-submit face replay. Regulile (24h, locație, ultima ștampilă) le verifică VoidService.
    Parametru: $transaction.
--}}

<div x-data="{ open: false, sending: false }" class="text-right">
    <button type="button" x-show="! open" @click="open = true; $nextTick(() => $refs.reason.focus())"
            class="text-sm font-medium text-red-600 hover:underline">Anulează</button>

    <form method="POST" action="{{ route('dashboard.transactions.void', $transaction) }}" x-show="open" x-cloak
          @submit="sending = true" class="mt-2 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <label class="sr-only" for="reason-{{ $transaction->id }}">Motivul anulării</label>
        <input id="reason-{{ $transaction->id }}" x-ref="reason" name="reason" type="text" maxlength="200" placeholder="Motiv (opțional)"
               class="min-w-0 flex-1 rounded-xl border-espresso-200 text-sm shadow-sm focus:border-gold-500 focus:ring-gold-500">
        <div class="flex shrink-0 gap-2">
            <button class="btn-danger px-3 py-2 text-sm" :disabled="sending">Confirmă anularea</button>
            <button type="button" class="btn-outline px-3 py-2 text-sm" @click="open = false">Renunță</button>
        </div>
    </form>
</div>
