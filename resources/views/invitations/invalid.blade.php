<x-guest-layout>
    <h1 class="text-lg font-medium text-gray-900">Invitația nu poate fi folosită</h1>
    <p class="mt-2 text-sm text-gray-700">{{ $message }}</p>

    @auth
        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <a href="{{ route('dashboard') }}" class="text-sm text-indigo-600 hover:underline">Mergi la dashboard</a>
            <button class="ms-4 text-sm text-gray-600 hover:underline">Ieși din cont</button>
        </form>
    @else
        <a href="{{ route('login') }}" class="mt-4 inline-block text-sm text-indigo-600 hover:underline">Autentificare</a>
    @endauth
</x-guest-layout>
