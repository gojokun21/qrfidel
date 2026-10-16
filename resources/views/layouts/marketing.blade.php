<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('layouts.partials.head', ['title' => $title ?? '', 'script' => true])
        @isset($description)
            <meta name="description" content="{{ $description }}">
        @endisset
    </head>
    <body class="bg-paper-50 font-sans text-graphite-900 antialiased">
        {{-- Header fix, translucid, ca pe kof.ru; devine opac după primul scroll. --}}
        <header x-data="{ scrolled: false, open: false }" x-init="scrolled = window.scrollY > 24" @scroll.window="scrolled = window.scrollY > 24"
                :class="scrolled || open ? 'bg-graphite-950/90 shadow-lg shadow-black/10 backdrop-blur-md' : 'bg-transparent'"
                class="fixed inset-x-0 top-0 z-50 transition-colors duration-300">
            <div class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8">
                <a href="{{ route('home') }}" aria-label="Creative Loyalty – acasă"><x-brand.wordmark dark /></a>

                <nav class="hidden items-center gap-8 text-sm font-medium text-paper-100 md:flex" aria-label="Secțiuni">
                    <a href="#cum-functioneaza" class="transition hover:text-honey-400">Cum funcționează</a>
                    <a href="#pentru-cine" class="transition hover:text-honey-400">Pentru cine</a>
                    <a href="#anti-frauda" class="transition hover:text-honey-400">Anti-fraudă</a>
                </nav>

                <div class="hidden items-center gap-3 md:flex">
                    @auth
                        <x-ui.button :href="route('dashboard')">Dashboard <x-ui.icon name="arrow-right" class="h-4 w-4" /></x-ui.button>
                    @else
                        <a href="{{ route('customer.login') }}" class="px-3 py-2 text-sm font-semibold text-paper-50 transition hover:text-honey-400">Cardurile mele</a>
                        <a href="{{ route('login') }}" class="px-3 py-2 text-sm font-semibold text-paper-50 transition hover:text-honey-400">Intră în cont</a>
                        <x-ui.button :href="route('register')">Începe gratuit</x-ui.button>
                    @endauth
                </div>

                <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-expanded="false" aria-controls="meniu-mobil"
                        class="rounded-lg p-2 text-paper-50 md:hidden">
                    <span class="sr-only">Meniu</span>
                    <x-ui.icon name="menu" class="h-7 w-7" x-show="! open" />
                    <x-ui.icon name="x" class="h-7 w-7" x-show="open" x-cloak />
                </button>
            </div>

            <div id="meniu-mobil" x-show="open" x-cloak x-transition @click="open = false" class="border-t border-graphite-800 px-5 pb-6 md:hidden">
                <nav class="flex flex-col gap-1 py-4 text-lg font-medium text-paper-50">
                    <a href="#cum-functioneaza" class="py-2">Cum funcționează</a>
                    <a href="#pentru-cine" class="py-2">Pentru cine</a>
                    <a href="#anti-frauda" class="py-2">Anti-fraudă</a>
                    <a href="{{ route('customer.login') }}" class="py-2">Cardurile mele</a>
                </nav>
                @auth
                    <x-ui.button :href="route('dashboard')" class="w-full">Dashboard</x-ui.button>
                @else
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.button :href="route('login')" variant="inverse">Intră în cont</x-ui.button>
                        <x-ui.button :href="route('register')">Începe gratuit</x-ui.button>
                    </div>
                @endauth
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="bg-black text-paper-100">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 py-16 sm:grid-cols-2 sm:px-8 lg:grid-cols-4">
                <div class="space-y-4">
                    <x-brand.wordmark dark />
                    <p class="max-w-xs text-sm leading-relaxed text-graphite-300">Carduri de fidelitate digitale pentru afaceri locale. Fără aplicație, fără hârtie.</p>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-paper-50">Produs</h3>
                    <ul class="mt-4 space-y-3 text-sm text-graphite-300">
                        <li><a href="#cum-functioneaza" class="hover:text-honey-400">Cum funcționează</a></li>
                        <li><a href="#pentru-cine" class="hover:text-honey-400">Pentru cine</a></li>
                        <li><a href="#anti-frauda" class="hover:text-honey-400">Anti-fraudă</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-paper-50">Cont</h3>
                    <ul class="mt-4 space-y-3 text-sm text-graphite-300">
                        @auth
                            <li><a href="{{ route('dashboard') }}" class="hover:text-honey-400">Dashboard</a></li>
                            <li><a href="{{ route('profile.edit') }}" class="hover:text-honey-400">Profil</a></li>
                        @else
                            <li><a href="{{ route('login') }}" class="hover:text-honey-400">Intră în cont</a></li>
                            <li><a href="{{ route('register') }}" class="hover:text-honey-400">Creează cont de afacere</a></li>
                        @endauth
                    </ul>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-paper-50">Pentru clienți</h3>
                    <p class="mt-4 text-sm leading-relaxed text-graphite-300">Scanează QR-ul de la tejghea sau intră pe pagina afacerii și primești cardul pe email, în câteva secunde.</p>
                </div>
            </div>
            <div class="border-t border-graphite-800">
                <div class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-6 text-xs text-graphite-400 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                    <p>© {{ now()->year }} Creative Loyalty. Toate drepturile rezervate.</p>
                    <p>Făcut pentru cafenele, restaurante, spălătorii și frizerii.</p>
                </div>
            </div>
        </footer>
    </body>
</html>
