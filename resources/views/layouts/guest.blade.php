<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['title' => $title])
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="relative isolate flex min-h-screen flex-col items-center bg-inverse-deep bg-glow px-4 pb-12 pt-10 sm:justify-center sm:pt-0">
            <div class="grain pointer-events-none absolute inset-0 -z-10 opacity-5 mix-blend-overlay" aria-hidden="true"></div>

            <a href="{{ route('home') }}" aria-label="Creative Loyalty – acasă" class="rounded-control focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"><x-brand.wordmark dark /></a>

            <main class="mt-8 w-full overflow-hidden rounded-panel bg-surface p-6 shadow-overlay sm:max-w-md sm:p-8">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
