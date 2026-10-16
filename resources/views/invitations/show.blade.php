<x-guest-layout :title="'Invitație în echipa '.$business->name">
    <h1 class="text-lg font-semibold text-ink">Invitație în echipa {{ $business->name }}</h1>
    <p class="mt-2 text-sm text-ink-secondary">
        Ai fost invitat ca <strong class="text-ink">{{ $invitation->role->label() }}</strong> cu adresa <strong class="break-all text-ink">{{ $invitation->email }}</strong>.
    </p>

    <x-layout.flash class="mt-6" />

    <div class="mt-6">
        @if ($user && $emailMatches)
            <form method="POST" action="{{ route('invitations.accept', $token) }}">
                @csrf
                <x-ui.button>Acceptă invitația</x-ui.button>
            </form>
        @elseif ($user)
            <p class="text-sm text-ink-secondary">Ești autentificat ca {{ $user->email }}. Ieși din cont și intră cu {{ $invitation->email }} ca să accepți.</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <x-ui.button variant="secondary">Ieși din cont</x-ui.button>
            </form>
        @elseif ($accountExists)
            <p class="text-sm text-ink-secondary">Ai deja un cont cu această adresă. Autentifică-te și vei reveni aici ca să accepți.</p>
            <x-ui.button :href="route('login')" class="mt-4">Autentificare</x-ui.button>
        @else
            <p class="text-sm text-ink-secondary">Creează-ți contul ca să intri în echipă.</p>

            <form method="POST" action="{{ route('invitations.register', $token) }}" class="mt-4 space-y-5">
                @csrf

                <x-ui.field label="Email" for="invitation_email">
                    <x-ui.input id="invitation_email" type="email" :value="$invitation->email" disabled />
                </x-ui.field>

                <x-ui.field label="Numele tău" for="name">
                    <x-ui.input id="name" name="name" type="text" :value="old('name')" required autofocus autocomplete="name" />
                </x-ui.field>

                <x-ui.field label="Parolă" for="password">
                    <x-ui.input id="password" name="password" type="password" required autocomplete="new-password" />
                </x-ui.field>

                <x-ui.field label="Confirmă parola" for="password_confirmation">
                    <x-ui.input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
                </x-ui.field>

                <x-ui.button>Creează contul și acceptă</x-ui.button>
            </form>
        @endif
    </div>
</x-guest-layout>
