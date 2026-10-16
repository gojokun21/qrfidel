{{--
    Sidebar-ul dashboard-ului. Datele vin din App\View\Composers\SidebarComposer:
    $sidebarUser, $sidebarBusiness, $sidebarRole, $sidebarLinks, $sidebarOtherBusinesses.
    Pe desktop e fix în stânga; pe mobil e un drawer controlat de `sidebarOpen` din layouts/app.
--}}
<div x-show="sidebarOpen" x-cloak x-transition.opacity @click="sidebarOpen = false"
     class="fixed inset-0 z-40 bg-inverse-deep/60 lg:hidden" aria-hidden="true"></div>

<aside id="sidebar" data-sidebar aria-label="Navigare dashboard"
       {{-- `invisible` scoate drawer-ul închis din ordinea de Tab și din arborele de accesibilitate (doar pe mobil). --}}
       :class="sidebarOpen ? 'translate-x-0 visible' : '-translate-x-full invisible lg:visible'"
       class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-inverse text-inverse-fg transition-[transform,visibility] duration-200 ease-out lg:visible lg:translate-x-0">
    <div class="flex h-16 shrink-0 items-center justify-between px-6 lg:h-20">
        <a href="{{ route('dashboard') }}" class="rounded-control focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"><x-brand.wordmark dark /></a>
        <button type="button" x-ref="sidebarClose" @click="sidebarOpen = false"
                class="rounded-control p-2 text-inverse-muted hover:bg-inverse-raised hover:text-inverse-fg focus:outline-none focus-visible:ring-2 focus-visible:ring-focus lg:hidden">
            <span class="sr-only">Închide meniul</span>
            <x-ui.icon name="x" class="h-5 w-5" />
        </button>
    </div>

    {{-- Afacerea curentă + comutarea între afaceri. --}}
    <div class="px-4" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="if (open) { open = false; $refs.switcher.focus(); $event.stopPropagation() }">
        <button type="button" x-ref="switcher" @click="open = ! open" :aria-expanded="open.toString()" aria-expanded="false" aria-controls="business-switcher"
                class="flex w-full items-center gap-3 rounded-card border border-inverse-line bg-inverse-raised/60 p-3 text-left transition hover:border-inverse-muted/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">
            @if ($sidebarBusiness)
                <x-ui.avatar :name="$sidebarBusiness->name" />
            @else
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-primary text-primary-fg" aria-hidden="true"><x-ui.icon name="plus" class="h-5 w-5" /></span>
            @endif
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold">{{ $sidebarBusiness?->name ?? 'Nicio afacere activă' }}</span>
                <span class="block text-xs text-inverse-muted">{{ $sidebarRole?->label() ?? 'Alege sau creează o afacere' }}</span>
            </span>
            <x-ui.icon name="chevron-up-down" class="h-4 w-4 shrink-0 text-inverse-muted" />
        </button>

        <div id="business-switcher" x-show="open" x-cloak x-transition.origin.top
             class="mt-2 overflow-hidden rounded-card border border-inverse-line bg-inverse-raised py-1 shadow-overlay">
            @foreach ($sidebarOtherBusinesses as $other)
                <form method="POST" action="{{ route('dashboard.business.switch') }}">
                    @csrf
                    <input type="hidden" name="business_id" value="{{ $other->id }}">
                    <button class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm text-inverse-fg hover:bg-inverse-line focus:bg-inverse-line focus:outline-none">
                        <x-ui.avatar :name="$other->name" size="sm" />
                        <span class="truncate">Comută la {{ $other->name }}</span>
                    </button>
                </form>
            @endforeach
            <a href="{{ route('dashboard.business.create') }}" class="flex items-center gap-3 px-3 py-2 text-sm text-accent-hover hover:bg-inverse-line focus:bg-inverse-line focus:outline-none">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg border border-dashed border-inverse-muted/60" aria-hidden="true"><x-ui.icon name="plus" class="h-4 w-4" /></span>
                Afacere nouă
            </a>
        </div>
    </div>

    <nav class="mt-6 flex-1 space-y-1 overflow-y-auto px-3" aria-label="Meniu principal">
        @if ($sidebarLinks !== [])
            <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-eyebrow text-inverse-muted">Meniu</p>
        @endif
        @foreach ($sidebarLinks as $link)
            <x-layout.sidebar-link :href="route($link['route'])" :icon="$link['icon']" :active="request()->routeIs(...(array) $link['pattern'])">
                {{ $link['label'] }}
            </x-layout.sidebar-link>
        @endforeach
    </nav>

    {{-- Contul userului. --}}
    <div class="border-t border-inverse-raised p-3">
        <x-layout.sidebar-link :href="route('profile.edit')" icon="user" :active="request()->routeIs('profile.*')">
            <span class="block truncate">{{ $sidebarUser->name }}</span>
            <span class="block truncate text-xs font-normal text-inverse-muted">{{ $sidebarUser->email }}</span>
        </x-layout.sidebar-link>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="mt-1 flex w-full items-center gap-3 rounded-control px-3 py-2.5 text-sm font-medium text-inverse-muted transition hover:bg-inverse-raised/60 hover:text-inverse-fg focus:outline-none focus-visible:ring-2 focus-visible:ring-focus">
                <x-ui.icon name="logout" class="h-5 w-5" />
                Deconectare
            </button>
        </form>
    </div>
</aside>
