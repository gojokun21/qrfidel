{{--
    Formularul de magic link. Cu `$business`, linkul aduce clientul înapoi pe pagina afacerii.
    După trimitere arată confirmarea (același mesaj pentru orice email: nu dezvăluim cine are cont).
--}}
@if (session('magic_link_sent'))
    <div class="rounded-2xl border border-gold-200 bg-gold-50 p-5" role="status">
        <p class="font-semibold text-espresso-900">Verifică-ți emailul</p>
        <p class="mt-1 text-sm text-espresso-700">
            Ți-am trimis un link de conectare la <strong class="break-all">{{ session('magic_link_sent') }}</strong>.
            E valabil {{ config('loyalty.magic_link.ttl_minutes') }} minute și se poate folosi o singură dată.
        </p>
    </div>
@else
    <form method="POST" action="{{ route('customer.magic.send') }}" class="space-y-3" x-data="{ sending: false }" @submit="sending = true">
        @csrf
        @isset($business)
            <input type="hidden" name="business" value="{{ $business->slug }}">
        @endisset

        <div>
            <label for="email" class="block text-sm font-medium text-espresso-800">Adresa ta de email</label>
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="email" inputmode="email"
                          placeholder="nume@exemplu.ro" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <button class="btn-gold w-full" :disabled="sending">Primește linkul de conectare</button>
        <p class="text-center text-xs text-espresso-500">Fără parolă: îți trimitem un link pe email.</p>
    </form>
@endif
