<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['title' => $title])
    </head>
    <body class="font-sans text-ink antialiased">
        <a href="#continut" class="sr-only z-skip rounded-control bg-surface px-4 py-2 font-semibold text-ink focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Sari la conținut</a>

        {{-- Paginile de cont ale staff-ului: același fundal pastel ca landing-ul, cardul alb în centru. --}}
        <div class="relative isolate flex min-h-screen flex-col bg-hero">
            <div class="absolute inset-0 -z-10 bg-dots bg-[length:22px_22px] [mask-image:radial-gradient(ellipse_at_center,black_10%,transparent_65%)]" aria-hidden="true"></div>

            <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8 sm:py-6">
                {{-- Logo-ul duce tot acasă, ca „Înapoi la site”; e scos din ordinea de Tab ca să nu fie două opriri identice. --}}
                <a href="{{ route('home') }}" tabindex="-1" aria-hidden="true"><x-brand.wordmark class="h-8" /></a>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 rounded-full border border-white bg-white/70 px-4 py-2 text-sm font-semibold text-ink-secondary shadow-soft backdrop-blur transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">
                    <x-ui.icon name="arrow-right" class="h-4 w-4 rotate-180" /> Înapoi la site
                </a>
            </header>

            <div class="flex flex-1 flex-col items-center px-4 pb-12 pt-4 sm:justify-center sm:pt-0">
                <main id="continut" tabindex="-1" class="w-full rounded-[1.75rem] focus:outline-none border border-white bg-white/90 p-6 shadow-float backdrop-blur sm:max-w-md sm:p-9">
                    @if ($heading)
                        <div class="mb-7">
                            <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ $heading }}</h1>
                            @if ($subheading)
                                <p class="mt-1.5 text-sm text-ink-secondary">{{ $subheading }}</p>
                            @endif
                        </div>
                    @endif

                    {{ $slot }}
                </main>

                {{-- Doar vizitatorilor: clientul logat are deja „Cardurile mele” (de ex. pe paginile de eroare). --}}
                @if ($account && ! auth('web')->check() && ! auth('customer')->check())
                    <p class="mt-6 text-center text-sm text-ink-secondary">
                        Ești client? <a href="{{ route('customer.login') }}" class="rounded-sm font-semibold text-primary-ink hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">Vezi cardurile tale</a>
                    </p>
                @endif
            </div>
        </div>
    </body>
</html>
