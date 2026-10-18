<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['title' => $title ?? ''])
        @vite('resources/js/landing.js')
        @isset($description)
            <meta name="description" content="{{ $description }}">
        @endisset
    </head>
    <body class="bg-white font-sans text-ink antialiased">
        <a href="#continut" class="sr-only z-skip rounded-control bg-surface px-4 py-2 font-semibold text-ink focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Sari la conținut</a>

        @php
            $sections = ['#cum-functioneaza' => 'Cum funcționează', '#pentru-cine' => 'Pentru cine', '#anti-frauda' => 'Anti-fraudă'];
        @endphp

        {{-- Meniul-pastilă (Figma „Landing v3”): stă în containerul hero-ului; după 16px de scroll urcă și devine compact. --}}
        <header x-data="{ scrolled: false, open: false }" x-init="scrolled = window.scrollY > 16" @scroll.window="scrolled = window.scrollY > 16"
                @keydown.escape.window="open = false"
                :class="scrolled && '-translate-y-4 lg:-translate-y-6'"
                class="fixed inset-x-0 top-7 z-50 px-7 transition-transform duration-300 lg:top-9 lg:px-16">
            <div :class="scrolled || open ? 'shadow-float' : 'shadow-[0_8px_12px_rgb(1_33_86/0.08),0_1px_1px_rgb(1_33_86/0.04)]'"
                 class="mx-auto max-w-[82rem] rounded-[1.75rem] bg-white transition-shadow duration-300">
                <div class="flex items-center justify-between gap-4 py-2.5 pl-[1.375rem] pr-2.5">
                    <a href="{{ route('home') }}" aria-label="LoyUp – acasă" class="rounded-control focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">
                        <x-brand.lockup />
                    </a>

                    <nav class="hidden items-center gap-9 text-[0.9375rem] font-medium text-steel-600 lg:flex" aria-label="Secțiuni">
                        @foreach ($sections as $href => $label)
                            <a href="{{ $href }}" class="rounded-sm transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">{{ $label }}</a>
                        @endforeach
                        <a href="{{ route('customer.login') }}" class="rounded-sm transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">Cardurile mele</a>
                    </nav>

                    <div class="flex items-center gap-5">
                        @auth
                            <x-marketing.button :href="route('dashboard')" variant="navy" size="sm" class="hidden md:inline-flex">Dashboard</x-marketing.button>
                        @else
                            <a href="{{ route('login') }}" class="hidden rounded-sm text-[0.9375rem] font-semibold text-ink transition hover:text-jade-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus md:inline">Intră în cont</a>
                            <x-marketing.button :href="route('register')" variant="navy" size="sm" class="hidden md:inline-flex">Începe gratuit</x-marketing.button>
                        @endauth

                        <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-expanded="false" aria-controls="meniu-mobil"
                                class="flex h-11 w-11 items-center justify-center rounded-full text-ink transition hover:bg-steel-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus lg:hidden">
                            <span class="sr-only">Meniu</span>
                            <x-ui.icon name="menu" class="h-6 w-6" x-show="! open" />
                            <x-ui.icon name="x" class="h-6 w-6" x-show="open" x-cloak />
                        </button>
                    </div>
                </div>

                <div id="meniu-mobil" x-show="open" x-cloak x-transition.opacity @click="open = false" class="border-t border-steel-100 px-5 pb-5 lg:hidden">
                    <nav class="flex flex-col py-3 text-base font-medium text-ink" aria-label="Meniu">
                        @foreach ($sections as $href => $label)
                            <a href="{{ $href }}" class="py-2.5">{{ $label }}</a>
                        @endforeach
                        <a href="{{ route('customer.login') }}" class="py-2.5">Cardurile mele</a>
                    </nav>
                    @auth
                        <x-marketing.button :href="route('dashboard')" variant="navy" class="w-full">Dashboard</x-marketing.button>
                    @else
                        <div class="grid grid-cols-2 gap-3">
                            <x-marketing.button :href="route('login')" variant="light">Intră în cont</x-marketing.button>
                            <x-marketing.button :href="route('register')" variant="navy">Începe gratuit</x-marketing.button>
                        </div>
                    @endauth
                </div>
            </div>
        </header>

        <main id="continut" tabindex="-1" class="mx-auto max-w-[90rem] focus:outline-none">
            {{ $slot }}
        </main>

        <footer class="mx-auto max-w-[90rem] px-5 pb-10 pt-16 sm:px-8 lg:px-[7.5rem] lg:pt-[4.5rem]">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-[21.25rem_1fr_1fr_1fr] lg:gap-16">
                <div class="space-y-4">
                    <x-brand.lockup size="lg" />
                    <p class="max-w-xs text-[0.9375rem] leading-relaxed text-steel-600">Carduri de fidelitate digitale pentru afaceri locale. Fără aplicație, fără hârtie.</p>
                </div>
                <div>
                    <h2 class="text-[0.8125rem] font-semibold uppercase tracking-[0.08em] text-ink">Produs</h2>
                    <ul class="mt-3 space-y-3 text-[0.9375rem] text-steel-600">
                        @foreach ($sections as $href => $label)
                            <li><a href="{{ $href }}" class="transition hover:text-jade-700">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h2 class="text-[0.8125rem] font-semibold uppercase tracking-[0.08em] text-ink">Cont</h2>
                    <ul class="mt-3 space-y-3 text-[0.9375rem] text-steel-600">
                        @auth
                            <li><a href="{{ route('dashboard') }}" class="transition hover:text-jade-700">Dashboard</a></li>
                            <li><a href="{{ route('profile.edit') }}" class="transition hover:text-jade-700">Profil</a></li>
                        @else
                            <li><a href="{{ route('login') }}" class="transition hover:text-jade-700">Intră în cont</a></li>
                            <li><a href="{{ route('register') }}" class="transition hover:text-jade-700">Creează cont de afacere</a></li>
                        @endauth
                        <li><a href="{{ route('customer.login') }}" class="transition hover:text-jade-700">Cardurile mele</a></li>
                    </ul>
                </div>
                <div>
                    <h2 class="text-[0.8125rem] font-semibold uppercase tracking-[0.08em] text-ink">Pentru clienți</h2>
                    <p class="mt-3 text-[0.9375rem] leading-relaxed text-steel-600">Scanează QR-ul de la tejghea sau intră pe pagina afacerii și primești cardul pe email, în câteva secunde.</p>
                </div>
            </div>
            <div class="mt-12 flex flex-col gap-2 border-t border-steel-100 pt-6 text-sm text-steel-500 sm:flex-row sm:justify-between">
                <p>© {{ now()->year }} LoyUp. Toate drepturile rezervate.</p>
                <p>Făcut pentru cafenele, restaurante, spălătorii și frizerii.</p>
            </div>
        </footer>
    </body>
</html>
