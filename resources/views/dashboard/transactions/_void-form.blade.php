{{--
    Anularea unei tranzacții (SDD §5.6, ADR 013), pe pagina „Tranzacții” și pe ecranul cardului.
    „Anulează” deschide motivul (opțional) și confirmarea; cheia de idempotency e generată la randare,
    deci un dublu-submit face replay. Regulile (24h, locație, ultima ștampilă) le verifică VoidService.
    „Renunță” și Escape închid formularul și întorc focusul pe „Anulează”.
    Parametru: $transaction.
--}}

<div x-data="{ open: false, sending: false }" class="sm:text-right"
     @keydown.escape="if (open) { open = false; $nextTick(() => $refs.toggle.focus()) }">
    <x-ui.button type="button" variant="danger-ghost" size="sm" x-ref="toggle" x-show="! open"
                 @click="open = true; $nextTick(() => $refs.reason.focus())"
                 aria-controls="void-{{ $transaction->id }}" ::aria-expanded="open.toString()">Anulează</x-ui.button>

    <form id="void-{{ $transaction->id }}" method="POST" action="{{ route('dashboard.transactions.void', $transaction) }}" x-show="open" x-cloak
          @submit="sending = true" class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <label class="sr-only" for="reason-{{ $transaction->id }}">Motivul anulării</label>
        <x-ui.input id="reason-{{ $transaction->id }}" x-ref="reason" name="reason" type="text" maxlength="200" placeholder="Motiv (opțional)"
                    class="min-w-0 flex-1 text-sm" />
        <div class="flex shrink-0 gap-2">
            <x-ui.button variant="danger" size="sm" ::disabled="sending">Confirmă anularea</x-ui.button>
            <x-ui.button type="button" variant="secondary" size="sm" @click="open = false; $nextTick(() => $refs.toggle.focus())">Renunță</x-ui.button>
        </div>
    </form>
</div>
