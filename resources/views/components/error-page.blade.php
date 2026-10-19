@props([
    'title',
    'message',
    'code' => null,
    'account' => true,
])

{{--
    Paginile de eroare (resources/views/errors): în română și cu o cale de ieșire (raport QA 2026-10-18, B2–B4).
    Staff-ul logat primește „Profil” și „Deconectare” (de ex. când afacerea e suspendată), vizitatorul „Autentificare”.
    Slotul adaugă acțiunea principală a paginii (de ex. „Înapoi” pe 419).
    `account=false` (500, 503): fără acțiunile de cont, care ar citi userul din baza de date poate căzută.
--}}
<x-guest-layout :title="$title" :account="$account">
    <div class="text-center">
        @if ($code)
            <p class="text-sm font-semibold uppercase tracking-eyebrow text-primary-ink">Eroare {{ $code }}</p>
        @endif
        <h1 class="mt-2 text-xl font-bold text-ink">{{ $title }}</h1>
        <p class="mt-2 text-sm text-ink-secondary">{{ $message }}</p>

        <div class="mt-6 flex flex-col gap-3">
            {{ $slot }}

            @if (! $account)
                <x-ui.button :href="route('home')" variant="secondary">Mergi la pagina principală</x-ui.button>
            @elseauth('web')
                <x-ui.button :href="route('profile.edit')" variant="secondary">Profil</x-ui.button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.button variant="ghost" class="w-full">Deconectare</x-ui.button>
                </form>
            @else
                @auth('customer')
                    <x-ui.button :href="route('customer.cards')" variant="secondary">Cardurile mele</x-ui.button>
                @else
                    <x-ui.button :href="route('login')" variant="secondary">Autentificare</x-ui.button>
                @endauth
            @endif
        </div>
    </div>
</x-guest-layout>
