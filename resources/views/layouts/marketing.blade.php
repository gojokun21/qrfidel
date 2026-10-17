<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('layouts.partials.head', ['title' => $title ?? ''])
        @isset($description)
            <meta name="description" content="{{ $description }}">
        @endisset
    </head>
    <body class="bg-white font-sans text-ink antialiased">
        <a href="#continut" class="sr-only z-skip rounded-control bg-surface px-4 py-2 font-semibold text-ink focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Sari la conținut</a>

        @php
            $sections = ['#cum-functioneaza' => 'Cum funcționează', '#pentru-cine' => 'Pentru cine', '#anti-frauda' => 'Anti-fraudă'];
        @endphp

        {{-- Meniul: o pastilă albă care plutește peste hero și devine opacă după primul scroll. --}}
        <header x-data="{ scrolled: false, open: false }" x-init="scrolled = window.scrollY > 16" @scroll.window="scrolled = window.scrollY > 16"
                @keydown.escape.window="open = false"
                class="fixed inset-x-0 top-0 z-50 px-3 pt-3 sm:px-6 sm:pt-5">
            <div :class="scrolled || open ? 'bg-white/95 shadow-float' : 'bg-white/70 shadow-soft'"
                 class="mx-auto max-w-6xl rounded-[1.75rem] border border-white/80 backdrop-blur-xl transition-[background-color,box-shadow] duration-300">
                <div class="grid h-16 grid-cols-[1fr_auto] items-center gap-4 pl-5 pr-2 lg:grid-cols-[1fr_auto_1fr] lg:pl-6">
                    <nav class="hidden items-center gap-7 text-sm font-medium text-ink-secondary lg:flex" aria-label="Secțiuni">
                        @foreach ($sections as $href => $label)
                            <a href="{{ $href }}" class="rounded-sm transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">{{ $label }}</a>
                        @endforeach
                    </nav>

                    <a href="{{ route('home') }}" aria-label="LoyUp – acasă" class="rounded-control justify-self-start focus:outline-none focus-visible:ring-2 focus-visible:ring-focus lg:justify-self-center">
                        <x-brand.wordmark class="h-8 w-auto" />
                    </a>

                    <div class="flex items-center justify-end gap-2">
                        @auth
                            <x-ui.button :href="route('dashboard')" variant="brand" pill class="hidden md:inline-flex">Dashboard <x-ui.icon name="arrow-right" class="h-4 w-4" /></x-ui.button>
                        @else
                            <a href="{{ route('customer.login') }}" class="hidden rounded-full px-3 py-2 text-sm font-semibold text-ink-secondary transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-focus xl:inline-flex">Cardurile mele</a>
                            <x-ui.button :href="route('login')" variant="light" pill class="hidden md:inline-flex">Intră în cont</x-ui.button>
                            <x-ui.button :href="route('register')" variant="brand" pill class="hidden md:inline-flex">Începe gratuit</x-ui.button>
                        @endauth

                        <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-expanded="false" aria-controls="meniu-mobil"
                                class="flex h-12 w-12 items-center justify-center rounded-full text-ink transition hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-focus lg:hidden">
                            <span class="sr-only">Meniu</span>
                            <x-ui.icon name="menu" class="h-6 w-6" x-show="! open" />
                            <x-ui.icon name="x" class="h-6 w-6" x-show="open" x-cloak />
                        </button>
                    </div>
                </div>

                <div id="meniu-mobil" x-show="open" x-cloak x-transition.opacity @click="open = false" class="border-t border-line px-5 pb-5 lg:hidden">
                    <nav class="flex flex-col py-3 text-base font-medium text-ink" aria-label="Meniu">
                        @foreach ($sections as $href => $label)
                            <a href="{{ $href }}" class="py-2.5">{{ $label }}</a>
                        @endforeach
                        <a href="{{ route('customer.login') }}" class="py-2.5">Cardurile mele</a>
                    </nav>
                    @auth
                        <x-ui.button :href="route('dashboard')" variant="brand" pill class="w-full">Dashboard</x-ui.button>
                    @else
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui.button :href="route('login')" variant="light" pill>Intră în cont</x-ui.button>
                            <x-ui.button :href="route('register')" variant="brand" pill>Începe gratuit</x-ui.button>
                        </div>
                    @endauth
                </div>
            </div>
        </header>

        <main id="continut" tabindex="-1" class="focus:outline-none">
            {{ $slot }}
        </main>

        <footer class="border-t border-line bg-white">
            <div class="mx-auto grid max-w-6xl gap-10 px-5 py-16 sm:grid-cols-2 sm:px-8 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr]">
                <div class="space-y-4">
                    <x-brand.wordmark class="h-9 w-auto" />
                    <p class="max-w-xs text-sm leading-relaxed text-ink-secondary">Carduri de fidelitate digitale pentru afaceri locale. Fără aplicație, fără hârtie.</p>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-ink">Produs</h2>
                    <ul class="mt-4 space-y-3 text-sm text-ink-secondary">
                        @foreach ($sections as $href => $label)
                            <li><a href="{{ $href }}" class="transition hover:text-primary-ink">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-ink">Cont</h2>
                    <ul class="mt-4 space-y-3 text-sm text-ink-secondary">
                        @auth
                            <li><a href="{{ route('dashboard') }}" class="transition hover:text-primary-ink">Dashboard</a></li>
                            <li><a href="{{ route('profile.edit') }}" class="transition hover:text-primary-ink">Profil</a></li>
                        @else
                            <li><a href="{{ route('login') }}" class="transition hover:text-primary-ink">Intră în cont</a></li>
                            <li><a href="{{ route('register') }}" class="transition hover:text-primary-ink">Creează cont de afacere</a></li>
                        @endauth
                        <li><a href="{{ route('customer.login') }}" class="transition hover:text-primary-ink">Cardurile mele</a></li>
                    </ul>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-ink">Pentru clienți</h2>
                    <p class="mt-4 text-sm leading-relaxed text-ink-secondary">Scanează QR-ul de la tejghea sau intră pe pagina afacerii și primești cardul pe email, în câteva secunde.</p>
                </div>
            </div>
            <div class="border-t border-line">
                <div class="mx-auto flex max-w-6xl flex-col gap-2 px-5 py-6 text-xs text-ink-muted sm:flex-row sm:items-center sm:justify-between sm:px-8">
                    <p>© {{ now()->year }} LoyUp. Toate drepturile rezervate.</p>
                    <p>Făcut pentru cafenele, restaurante, spălătorii și frizerii.</p>
                </div>
            </div>
        </footer>
    </body>
</html>
