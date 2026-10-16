<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gold-600">Bun venit, {{ auth()->user()->name }}</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-espresso-900">{{ $business->name }}</h2>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-espresso-900 px-3 py-1 text-xs font-semibold text-gold-300">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-400"></span>
                Rolul tău: {{ $role?->label() }}
            </span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-10 space-y-6">
            <x-flash />

            {{-- Pagina publică a afacerii (SDD §8: /b/{slug}). --}}
            <div class="relative isolate overflow-hidden rounded-3xl bg-espresso-900 p-6 text-cream-50 sm:p-8">
                <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top_right,rgba(204,153,51,0.35),transparent_60%)]" aria-hidden="true"></div>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm text-espresso-300">Pagina publică</p>
                        <p class="mt-1 truncate font-mono text-sm text-cream-50 sm:text-base">{{ url('/b/'.$business->slug) }}</p>
                    </div>
                    <x-icon name="link" class="hidden h-10 w-10 shrink-0 text-gold-500 sm:block" />
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-3">
                <x-card class="flex flex-col">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-espresso-500">Locații</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gold-50 text-gold-600"><x-icon name="map-pin" class="h-5 w-5" /></span>
                    </div>
                    <p class="mt-3 text-4xl font-bold tracking-tight text-espresso-900">{{ $locationsCount }}</p>
                    @if ($role?->atLeast(\App\Enums\Role::Manager))
                        <a href="{{ route('dashboard.locations.index') }}" class="link mt-auto pt-4 text-sm">Vezi locațiile</a>
                    @endif
                </x-card>
                <x-card class="flex flex-col">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-espresso-500">Membri în echipă</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gold-50 text-gold-600"><x-icon name="users" class="h-5 w-5" /></span>
                    </div>
                    <p class="mt-3 text-4xl font-bold tracking-tight text-espresso-900">{{ $membersCount }}</p>
                    @can('manageTeam', $business)
                        <a href="{{ route('dashboard.team.index') }}" class="link mt-auto pt-4 text-sm">Gestionează echipa</a>
                    @endcan
                </x-card>
                <x-card class="flex flex-col">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-espresso-500">Invitații în așteptare</p>
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gold-50 text-gold-600"><x-icon name="mail" class="h-5 w-5" /></span>
                    </div>
                    <p class="mt-3 text-4xl font-bold tracking-tight text-espresso-900">{{ $pendingInvitationsCount }}</p>
                </x-card>
            </div>

            @can('update', $business)
                @if ($locationsCount === 0)
                    <div class="flex flex-col gap-4 rounded-3xl border-2 border-dashed border-gold-300 bg-gold-50 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                        <div>
                            <p class="text-lg font-semibold text-espresso-900">Primul pas</p>
                            <p class="mt-1 text-espresso-600">Începe prin a adăuga prima locație, apoi invită-ți echipa.</p>
                        </div>
                        <a href="{{ route('dashboard.locations.create') }}" class="btn-gold shrink-0"><x-icon name="plus" class="h-4 w-4" /> Adaugă o locație</a>
                    </div>
                @endif
            @endcan
        </div>
    </div>
</x-app-layout>
