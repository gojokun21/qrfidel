<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('layouts.partials.head', ['title' => $title ?? '', 'script' => true])
        @isset($description)
            <meta name="description" content="{{ $description }}">
        @endisset
    </head>
    <body class="bg-cream-50 font-sans text-espresso-900 antialiased">
        {{-- Header fix, translucid, ca pe kof.ru; devine opac după primul scroll. --}}
        <header x-data="{ scrolled: false, open: false }" x-init="scrolled = window.scrollY > 24" @scroll.window="scrolled = window.scrollY > 24"
                :class="scrolled || open ? 'bg-espresso-950/90 shadow-lg shadow-black/10 backdrop-blur-md' : 'bg-transparent'"
                class="fixed inset-x-0 top-0 z-50 transition-colors duration-300">
            <div class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8">
                <a href="{{ route('home') }}" aria-label="Creative Loyalty – acasă"><x-wordmark dark /></a>

                <nav class="hidden items-center gap-8 text-sm font-medium text-cream-100 md:flex" aria-label="Secțiuni">
                    <a href="#cum-functioneaza" class="transition hover:text-gold-400">Cum funcționează</a>
                    <a href="#pentru-cine" class="transition hover:text-gold-400">Pentru cine</a>
                    <a href="#anti-frauda" class="transition hover:text-gold-400">Anti-fraudă</a>
                </nav>

                <div class="hidden items-center gap-3 md:flex">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-gold">Dashboard <x-icon name="arrow-right" class="h-4 w-4" /></a>
                    @else
                        <a href="{{ route('customer.login') }}" class="px-3 py-2 text-sm font-semibold text-cream-50 transition hover:text-gold-400">Cardurile mele</a>
                        <a href="{{ route('login') }}" class="px-3 py-2 text-sm font-semibold text-cream-50 transition hover:text-gold-400">Intră în cont</a>
                        <a href="{{ route('register') }}" class="btn-gold">Începe gratuit</a>
                    @endauth
                </div>

                <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-expanded="false" aria-controls="meniu-mobil"
                        class="rounded-lg p-2 text-cream-50 md:hidden">
                    <span class="sr-only">Meniu</span>
                    <x-icon name="menu" class="h-7 w-7" x-show="! open" />
                    <x-icon name="x" class="h-7 w-7" x-show="open" x-cloak />
                </button>
            </div>

            <div id="meniu-mobil" x-show="open" x-cloak x-transition @click="open = false" class="border-t border-espresso-800 px-5 pb-6 md:hidden">
                <nav class="flex flex-col gap-1 py-4 text-lg font-medium text-cream-50">
                    <a href="#cum-functioneaza" class="py-2">Cum funcționează</a>
                    <a href="#pentru-cine" class="py-2">Pentru cine</a>
                    <a href="#anti-frauda" class="py-2">Anti-fraudă</a>
                    <a href="{{ route('customer.login') }}" class="py-2">Cardurile mele</a>
                </nav>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-gold w-full">Dashboard</a>
                @else
                    <div class="grid grid-cols-2 gap-3">
                        <a href="{{ route('login') }}" class="btn border border-espresso-600 text-cream-50">Intră în cont</a>
                        <a href="{{ route('register') }}" class="btn-gold">Începe gratuit</a>
                    </div>
                @endauth
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="bg-black text-cream-100">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 py-16 sm:grid-cols-2 sm:px-8 lg:grid-cols-4">
                <div class="space-y-4">
                    <x-wordmark dark />
                    <p class="max-w-xs text-sm leading-relaxed text-espresso-300">Carduri de fidelitate digitale pentru afaceri locale. Fără aplicație, fără hârtie.</p>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-cream-50">Produs</h3>
                    <ul class="mt-4 space-y-3 text-sm text-espresso-300">
                        <li><a href="#cum-functioneaza" class="hover:text-gold-400">Cum funcționează</a></li>
                        <li><a href="#pentru-cine" class="hover:text-gold-400">Pentru cine</a></li>
                        <li><a href="#anti-frauda" class="hover:text-gold-400">Anti-fraudă</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-cream-50">Cont</h3>
                    <ul class="mt-4 space-y-3 text-sm text-espresso-300">
                        @auth
                            <li><a href="{{ route('dashboard') }}" class="hover:text-gold-400">Dashboard</a></li>
                            <li><a href="{{ route('profile.edit') }}" class="hover:text-gold-400">Profil</a></li>
                        @else
                            <li><a href="{{ route('login') }}" class="hover:text-gold-400">Intră în cont</a></li>
                            <li><a href="{{ route('register') }}" class="hover:text-gold-400">Creează cont de afacere</a></li>
                        @endauth
                    </ul>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-cream-50">Pentru clienți</h3>
                    <p class="mt-4 text-sm leading-relaxed text-espresso-300">Scanează QR-ul de la tejghea sau intră pe pagina afacerii și primești cardul pe email, în câteva secunde.</p>
                </div>
            </div>
            <div class="border-t border-espresso-800">
                <div class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-6 text-xs text-espresso-400 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                    <p>© {{ now()->year }} Creative Loyalty. Toate drepturile rezervate.</p>
                    <p>Făcut pentru cafenele, restaurante, spălătorii și frizerii.</p>
                </div>
            </div>
        </footer>
    </body>
</html>
