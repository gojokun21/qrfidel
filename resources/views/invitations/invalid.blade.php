<x-guest-layout title="Invitație invalidă">
    <h1 class="text-lg font-semibold text-ink">Invitația nu poate fi folosită</h1>
    <p class="mt-2 text-sm text-ink-secondary">{{ $message }}</p>

    @auth
        <form method="POST" action="{{ route('logout') }}" class="mt-6 flex flex-wrap items-center gap-3">
            @csrf
            <x-ui.button :href="route('dashboard')" variant="secondary">Mergi la dashboard</x-ui.button>
            <x-ui.button variant="ghost">Ieși din cont</x-ui.button>
        </form>
    @else
        <x-ui.button :href="route('login')" class="mt-6">Autentificare</x-ui.button>
    @endauth
</x-guest-layout>
