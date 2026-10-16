<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['title' => $title])
        @include('layouts.partials.pwa-head')
    </head>
    <body class="min-h-screen bg-cream-50 font-sans text-espresso-900 antialiased" data-pwa>
        <header class="bg-espresso-950">
            <div class="mx-auto flex h-16 max-w-xl items-center justify-between gap-4 px-4">
                <a href="{{ auth('customer')->check() ? route('customer.cards') : route('home') }}" aria-label="Creative Loyalty – acasă"><x-wordmark dark /></a>

                @auth('customer')
                    <form method="POST" action="{{ route('customer.logout') }}">
                        @csrf
                        <button class="rounded-lg px-3 py-2 text-sm font-semibold text-cream-100 transition hover:text-gold-400">Ieși</button>
                    </form>
                @endauth
            </div>
        </header>

        <main class="mx-auto max-w-xl px-4 pb-16 pt-6">
            {{ $slot }}
        </main>
    </body>
</html>
