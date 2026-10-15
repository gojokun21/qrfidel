@php
    use App\Enums\Role;

    // Linkurile dashboard-ului depind de rolul în afacerea curentă (setată de middleware-ul `business`).
    $navBusiness = app(\App\Support\Tenancy\CurrentBusiness::class)->get();
    $navRole = $navBusiness ? Auth::user()->roleIn($navBusiness) : null;
    $navLinks = collect([
        ['route' => 'dashboard', 'label' => 'Dashboard', 'pattern' => 'dashboard', 'visible' => true],
        ['route' => 'dashboard.locations.index', 'label' => 'Locații', 'pattern' => 'dashboard.locations.*', 'visible' => $navRole?->atLeast(Role::Manager)],
        ['route' => 'dashboard.team.index', 'label' => 'Echipa', 'pattern' => 'dashboard.team.*', 'visible' => $navRole === Role::Owner],
        ['route' => 'dashboard.business.edit', 'label' => 'Afacerea', 'pattern' => 'dashboard.business.edit', 'visible' => $navRole === Role::Owner],
    ])->filter(fn (array $link) => $link['visible']);
    $navBusinesses = Auth::user()->businesses()->orderBy('name')->get();
@endphp
<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    @foreach ($navLinks as $link)
                        <x-nav-link :href="route($link['route'])" :active="request()->routeIs($link['pattern'])">
                            {{ $link['label'] }}
                        </x-nav-link>
                    @endforeach
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}@if ($navBusiness) <span class="text-gray-400">· {{ $navBusiness->name }}</span>@endif</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @foreach ($navBusinesses->reject(fn ($b) => $b->id === $navBusiness?->id) as $other)
                            <form method="POST" action="{{ route('dashboard.business.switch') }}">
                                @csrf
                                <input type="hidden" name="business_id" value="{{ $other->id }}">
                                <button class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100">Comută la {{ $other->name }}</button>
                            </form>
                        @endforeach
                        <x-dropdown-link :href="route('dashboard.business.create')">
                            Afacere nouă
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @foreach ($navLinks as $link)
                <x-responsive-nav-link :href="route($link['route'])" :active="request()->routeIs($link['pattern'])">
                    {{ $link['label'] }}
                </x-responsive-nav-link>
            @endforeach
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                @foreach ($navBusinesses->reject(fn ($b) => $b->id === $navBusiness?->id) as $other)
                    <form method="POST" action="{{ route('dashboard.business.switch') }}">
                        @csrf
                        <input type="hidden" name="business_id" value="{{ $other->id }}">
                        <button class="block w-full ps-3 pe-4 py-2 text-start text-base font-medium text-gray-600 hover:bg-gray-50">Comută la {{ $other->name }}</button>
                    </form>
                @endforeach
                <x-responsive-nav-link :href="route('dashboard.business.create')">
                    Afacere nouă
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
