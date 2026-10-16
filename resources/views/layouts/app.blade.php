<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="bg-cream-50 font-sans text-espresso-900 antialiased">
        <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" class="min-h-screen">
            @include('layouts.sidebar')

            <div class="lg:pl-72">
                {{-- Bara de sus pe mobil: logo + butonul care deschide sidebar-ul. --}}
                <div class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-espresso-800 bg-espresso-900 px-4 lg:hidden">
                    <a href="{{ route('dashboard') }}"><x-wordmark dark /></a>
                    <button type="button" @click="sidebarOpen = true" aria-controls="sidebar" aria-expanded="false" :aria-expanded="sidebarOpen.toString()"
                            class="rounded-lg p-2 text-cream-50 hover:bg-espresso-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-500">
                        <span class="sr-only">Deschide meniul</span>
                        <x-icon name="menu" class="h-6 w-6" />
                    </button>
                </div>

                @isset($header)
                    <header class="border-b border-espresso-100 bg-white">
                        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-10">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
