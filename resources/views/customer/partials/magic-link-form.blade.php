{{--
    Formularul de magic link. Cu `$business`, linkul aduce clientul înapoi pe pagina afacerii;
    cu `$counterLocation`, înapoi la tejghea (ADR 012).
    După trimitere arată confirmarea (același mesaj pentru orice email: nu dezvăluim cine are cont).
--}}
@if (session('magic_link_sent'))
    <x-ui.alert tone="success" title="Verifică-ți emailul">
        Ți-am trimis un link de conectare la <strong class="break-words">{{ session('magic_link_sent') }}</strong>.
        E valabil {{ config('loyalty.magic_link.ttl_minutes') }} minute și se poate folosi o singură dată.
    </x-ui.alert>
@else
    <form method="POST" action="{{ route('customer.magic.send') }}" class="space-y-3" x-data="{ sending: false }" @submit="sending = true">
        @csrf
        @isset($business)
            <input type="hidden" name="business" value="{{ $business->slug }}">
        @endisset
        @isset($counterLocation)
            <input type="hidden" name="location" value="{{ $counterLocation->counter_token }}">
        @endisset

        <x-ui.field label="Adresa ta de email" for="email">
            <x-ui.input id="email" type="email" name="email" :value="old('email')" required autocomplete="email" inputmode="email"
                        placeholder="nume@exemplu.ro" />
        </x-ui.field>

        <x-ui.button class="w-full" ::disabled="sending">Primește linkul de conectare</x-ui.button>
        <p class="text-center text-xs text-ink-muted">Fără parolă: îți trimitem un link pe email.</p>
    </form>
@endif
