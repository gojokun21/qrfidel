<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="font-sans text-espresso-900 antialiased">
        <div class="relative isolate flex min-h-screen flex-col items-center bg-espresso-950 px-4 pb-12 pt-10 sm:justify-center sm:pt-0">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_70%_10%,rgba(204,153,51,0.3),transparent_55%),radial-gradient(ellipse_at_0%_100%,rgba(204,153,51,0.15),transparent_50%)]" aria-hidden="true"></div>
            <div class="grain pointer-events-none absolute inset-0 -z-10 opacity-[0.06] mix-blend-overlay" aria-hidden="true"></div>

            <a href="{{ route('home') }}" aria-label="Creative Loyalty – acasă"><x-wordmark dark /></a>

            <div class="mt-8 w-full overflow-hidden rounded-3xl bg-white p-6 shadow-2xl shadow-black/40 sm:max-w-md sm:p-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
