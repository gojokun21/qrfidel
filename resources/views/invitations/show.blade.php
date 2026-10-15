<x-guest-layout>
    <h1 class="text-lg font-medium text-gray-900">Invitație în echipa {{ $business->name }}</h1>
    <p class="mt-2 text-sm text-gray-700">
        Ai fost invitat ca <strong>{{ $invitation->role->label() }}</strong> cu adresa <strong>{{ $invitation->email }}</strong>.
    </p>

    <div class="mt-6">
        <x-flash />
    </div>

    @if ($user && $emailMatches)
        <form method="POST" action="{{ route('invitations.accept', $token) }}">
            @csrf
            <x-primary-button>Acceptă invitația</x-primary-button>
        </form>
    @elseif ($user)
        <p class="text-sm text-gray-700">Ești autentificat ca {{ $user->email }}. Ieși din cont și intră cu {{ $invitation->email }} ca să accepți.</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <x-secondary-button type="submit">Ieși din cont</x-secondary-button>
        </form>
    @elseif ($accountExists)
        <p class="text-sm text-gray-700">Ai deja un cont cu această adresă. Autentifică-te și vei reveni aici ca să accepți.</p>
        <a href="{{ route('login') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-gray-800 rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-gray-700">Autentificare</a>
    @else
        <p class="text-sm text-gray-700">Creează-ți contul ca să intri în echipă.</p>

        <form method="POST" action="{{ route('invitations.register', $token) }}" class="mt-4 space-y-4">
            @csrf

            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" type="email" class="mt-1 block w-full bg-gray-50" :value="$invitation->email" disabled />
            </div>

            <div>
                <x-input-label for="name" value="Numele tău" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="password" value="Parolă" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                <x-input-error class="mt-2" :messages="$errors->get('password')" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="Confirmă parola" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            </div>

            <x-primary-button>Creează contul și acceptă</x-primary-button>
        </form>
    @endif
</x-guest-layout>
