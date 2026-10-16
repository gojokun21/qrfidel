{{--
    Sidebar-ul dashboard-ului. Datele vin din App\View\Composers\SidebarComposer:
    $sidebarUser, $sidebarBusiness, $sidebarRole, $sidebarLinks, $sidebarOtherBusinesses.
    Pe desktop e fix în stânga; pe mobil e un drawer controlat de `sidebarOpen` din layouts/app.
--}}
<div x-show="sidebarOpen" x-cloak x-transition.opacity @click="sidebarOpen = false"
     class="fixed inset-0 z-40 bg-espresso-950/60 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

<aside id="sidebar" data-sidebar aria-label="Navigare dashboard"
       {{-- `invisible` scoate drawer-ul închis din ordinea de Tab și din arborele de accesibilitate (doar pe mobil). --}}
       :class="sidebarOpen ? 'translate-x-0 visible' : '-translate-x-full invisible lg:visible'"
       class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-espresso-900 text-cream-50 transition-[transform,visibility] duration-200 ease-out lg:visible lg:translate-x-0">
    <div class="flex h-16 shrink-0 items-center justify-between px-6 lg:h-20">
        <a href="{{ route('dashboard') }}"><x-wordmark dark /></a>
        <button type="button" @click="sidebarOpen = false" class="rounded-lg p-2 text-espresso-300 hover:bg-espresso-800 hover:text-cream-50 lg:hidden">
            <span class="sr-only">Închide meniul</span>
            <x-icon name="x" class="h-5 w-5" />
        </button>
    </div>

    {{-- Afacerea curentă + comutarea între afaceri. --}}
    <div class="px-4" x-data="{ open: false }" @click.outside="open = false">
        <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-expanded="false"
                class="flex w-full items-center gap-3 rounded-2xl border border-espresso-700 bg-espresso-800/60 p-3 text-left transition hover:border-espresso-600">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gold-500 text-base font-bold text-espresso-950">
                {{ $sidebarBusiness ? mb_strtoupper(mb_substr($sidebarBusiness->name, 0, 1)) : '+' }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold">{{ $sidebarBusiness?->name ?? 'Nicio afacere activă' }}</span>
                <span class="block text-xs text-espresso-300">{{ $sidebarRole?->label() ?? 'Alege sau creează o afacere' }}</span>
            </span>
            <x-icon name="chevron-up-down" class="h-4 w-4 shrink-0 text-espresso-400" />
        </button>

        <div x-show="open" x-cloak x-transition.origin.top
             class="mt-2 overflow-hidden rounded-2xl border border-espresso-700 bg-espresso-800 py-1 shadow-xl">
            @foreach ($sidebarOtherBusinesses as $other)
                <form method="POST" action="{{ route('dashboard.business.switch') }}">
                    @csrf
                    <input type="hidden" name="business_id" value="{{ $other->id }}">
                    <button class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm text-espresso-100 hover:bg-espresso-700">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-espresso-700 text-xs font-semibold text-gold-400">{{ mb_strtoupper(mb_substr($other->name, 0, 1)) }}</span>
                        <span class="truncate">Comută la {{ $other->name }}</span>
                    </button>
                </form>
            @endforeach
            <a href="{{ route('dashboard.business.create') }}" class="flex items-center gap-3 px-3 py-2 text-sm text-gold-400 hover:bg-espresso-700">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg border border-dashed border-espresso-500"><x-icon name="plus" class="h-4 w-4" /></span>
                Afacere nouă
            </a>
        </div>
    </div>

    <nav class="mt-6 flex-1 space-y-1 overflow-y-auto px-3" aria-label="Meniu principal">
        @if ($sidebarLinks !== [])
            <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-espresso-400">Meniu</p>
        @endif
        @foreach ($sidebarLinks as $link)
            <x-sidebar-link :href="route($link['route'])" :icon="$link['icon']" :active="request()->routeIs(...(array) $link['pattern'])">
                {{ $link['label'] }}
            </x-sidebar-link>
        @endforeach
    </nav>

    {{-- Contul userului. --}}
    <div class="border-t border-espresso-800 p-3">
        <x-sidebar-link :href="route('profile.edit')" icon="user" :active="request()->routeIs('profile.*')">
            <span class="block truncate">{{ $sidebarUser->name }}</span>
            <span class="block truncate text-xs font-normal text-espresso-400">{{ $sidebarUser->email }}</span>
        </x-sidebar-link>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="mt-1 flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-espresso-300 transition hover:bg-espresso-800/60 hover:text-cream-50">
                <x-icon name="logout" class="h-5 w-5 text-espresso-400" />
                Deconectare
            </button>
        </form>
    </div>
</aside>
