<x-app-layout :title="$business->name" :eyebrow="'Bun venit, '.auth()->user()->name">
    <x-slot:meta>
        <x-ui.badge tone="inverse">Rolul tău: {{ $role?->label() }}</x-ui.badge>
    </x-slot:meta>

    {{-- Pagina publică a afacerii (SDD §8: /b/{slug}). --}}
    <x-ui.card class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="text-sm font-medium text-ink-muted">Pagina publică</p>
            <a href="{{ url('/b/'.$business->slug) }}" target="_blank" rel="noopener"
               class="mt-1 block break-all text-sm text-ink underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-focus sm:text-base">{{ url('/b/'.$business->slug) }}</a>
        </div>
        <x-ui.icon name="link" class="hidden h-8 w-8 shrink-0 text-primary sm:block" />
    </x-ui.card>

    @if ($headline)
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat label="Ștampile azi" :value="$headline['stampsToday']" icon="clock" />
            <x-ui.stat label="Ștampile în 7 zile" :value="$headline['stampsWeek']" icon="star" />
            <x-ui.stat :label="'Clienți activi ('.mb_strtolower(\App\Services\Loyalty\ReportService::periodLabel($headlinePeriod)).')'" :value="$headline['activeCustomers']" icon="users">
                <x-ui.link :href="route('dashboard.reports')">Vezi rapoartele</x-ui.link>
            </x-ui.stat>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Locații" :value="$locationsCount" icon="map-pin">
            @if ($role?->atLeast(\App\Enums\Role::Manager))
                <x-ui.link :href="route('dashboard.locations.index')">Vezi locațiile</x-ui.link>
            @endif
        </x-ui.stat>
        <x-ui.stat label="Membri în echipă" :value="$membersCount" icon="users">
            @can('manageTeam', $business)
                <x-ui.link :href="route('dashboard.team.index')">Gestionează echipa</x-ui.link>
            @endcan
        </x-ui.stat>
        <x-ui.stat label="Invitații în așteptare" :value="$pendingInvitationsCount" icon="mail" />
    </div>

    @can('update', $business)
        @if ($locationsCount === 0)
            <div class="flex flex-col gap-4 rounded-card border-2 border-dashed border-primary-line bg-primary-soft p-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-lg font-semibold text-ink">Primul pas</p>
                <x-ui.button :href="route('dashboard.locations.create')" icon="plus" class="shrink-0">Adaugă o locație</x-ui.button>
            </div>
        @endif
    @endcan
</x-app-layout>
