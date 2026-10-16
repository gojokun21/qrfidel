<x-guest-layout>
    <h1 class="text-lg font-medium text-espresso-900">Invitația nu poate fi folosită</h1>
    <p class="mt-2 text-sm text-espresso-700">{{ $message }}</p>

    @auth
        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <a href="{{ route('dashboard') }}" class="text-sm link">Mergi la dashboard</a>
            <button class="ms-4 text-sm font-medium text-espresso-600 hover:text-espresso-900 hover:underline">Ieși din cont</button>
        </form>
    @else
        <a href="{{ route('login') }}" class="mt-4 inline-block text-sm link">Autentificare</a>
    @endauth
</x-guest-layout>
