<!DOCTYPE html>
<html lang="ro">
    <head>
        @include('layouts.partials.head', ['title' => $title, 'themeColor' => '#012156'])
    </head>
    <body class="bg-canvas font-sans text-ink antialiased">
        <a href="#continut" class="sr-only z-skip rounded-control bg-surface px-4 py-2 font-semibold text-ink focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Sari la conținut</a>

        {{-- Drawer-ul de pe mobil: la deschidere focusul intră în meniu, restul paginii devine `inert`; la închidere revine pe buton. --}}
        <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" class="min-h-screen"
             x-init="$watch('sidebarOpen', open => $nextTick(() => open ? $refs.sidebarClose.focus() : $refs.sidebarToggle.offsetParent && $refs.sidebarToggle.focus()))">
            @include('layouts.sidebar')

            <div class="lg:pl-72" :inert="sidebarOpen">
                {{-- Bara de sus pe mobil: logo + butonul care deschide sidebar-ul. --}}
                <div class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-inverse-raised bg-inverse px-4 lg:hidden">
                    <a href="{{ route('dashboard') }}" class="rounded-control focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"><x-brand.wordmark dark /></a>
                    <button type="button" x-ref="sidebarToggle" @click="sidebarOpen = true" aria-controls="sidebar" aria-expanded="false" :aria-expanded="sidebarOpen.toString()"
                            class="rounded-control p-2 text-inverse-fg hover:bg-inverse-raised focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">
                        <span class="sr-only">Deschide meniul</span>
                        <x-ui.icon name="menu" class="h-6 w-6" />
                    </button>
                </div>

                <main id="continut" tabindex="-1" class="focus:outline-none">
                    @if ($title !== '')
                        <header class="border-b border-line bg-surface">
                            <div class="mx-auto flex max-w-content flex-col gap-4 px-4 py-6 sm:flex-row sm:items-end sm:justify-between sm:px-6 lg:px-10">
                                <div class="min-w-0">
                                    @if ($back)
                                        <x-ui.link :href="$back" variant="muted" class="text-sm">← {{ $backLabel ?? 'Înapoi' }}</x-ui.link>
                                    @endif
                                    @if ($eyebrow)
                                        <p class="text-xs font-semibold uppercase tracking-eyebrow text-primary-ink">{{ $eyebrow }}</p>
                                    @endif
                                    <div @class(['flex flex-wrap items-center gap-x-3 gap-y-2', 'mt-1' => $back || $eyebrow])>
                                        <h1 class="min-w-0 break-words text-2xl font-bold tracking-tight text-ink">{{ $title }}</h1>
                                        {{ $meta ?? '' }}
                                    </div>
                                    @if ($description)
                                        <p class="mt-1 max-w-3xl text-sm text-ink-muted">{{ $description }}</p>
                                    @endif
                                </div>
                                @isset($actions)
                                    <div class="flex shrink-0 flex-wrap items-center gap-3">{{ $actions }}</div>
                                @endisset
                            </div>
                        </header>
                    @endif

                    <div class="mx-auto max-w-content px-4 py-6 sm:px-6 sm:py-8 lg:px-10">
                        <div class="{{ $widthClass() }} space-y-6">
                            <x-layout.flash />
                            {{ $slot }}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
