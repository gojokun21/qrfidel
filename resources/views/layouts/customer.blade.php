<!DOCTYPE html>
<html lang="ro">
    <head>
        @include('layouts.partials.head', ['title' => $title])
        @include('layouts.partials.pwa-head')
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased" data-pwa>
        <a href="#continut" class="sr-only z-skip rounded-control bg-surface px-4 py-2 font-semibold text-ink focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Sari la conținut</a>

        <header class="bg-inverse-deep">
            <div class="mx-auto flex h-16 max-w-xl items-center justify-between gap-4 px-4">
                <a href="{{ auth('customer')->check() ? route('customer.cards') : route('home') }}" aria-label="LoyUp – acasă"
                   class="rounded-control focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"><x-brand.wordmark dark /></a>

                @auth('customer')
                    <form method="POST" action="{{ route('customer.logout') }}">
                        @csrf
                        <button class="rounded-control px-3 py-2 text-sm font-semibold text-inverse-fg transition hover:text-accent-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">Ieși</button>
                    </form>
                @endauth
            </div>
        </header>

        {{-- Mesajele (status / eroarea `loyalty`) le pune fiecare pagină unde are sens, cu x-layout.flash. --}}
        <main id="continut" tabindex="-1" class="mx-auto max-w-xl space-y-6 px-4 pb-16 pt-6 focus:outline-none">
            {{ $slot }}
        </main>
    </body>
</html>
