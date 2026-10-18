{{-- „Rapoarte” (manager+, SDD §9, ADR 014). --}}
<x-app-layout title="Rapoarte"
              :description="$report->periodLabel().', de la '.$report->from->format('d.m.Y').' ('.$business->timezone.')'">
    @if ($restrictedTo)
        <x-slot:actions>
            <x-ui.badge>
                <x-ui.icon name="map-pin" class="h-3.5 w-3.5" /> Doar locația {{ $restrictedTo->name }}
            </x-ui.badge>
        </x-slot:actions>
    @endif

    <nav class="flex flex-wrap gap-2" aria-label="Perioada">
        @foreach ($periods as $days)
            <x-ui.filter-link :href="route('dashboard.reports', ['zile' => $days])" :active="$report->days === $days">{{ $days }} zile</x-ui.filter-link>
        @endforeach
    </nav>

    @include('dashboard.reports._summary', ['report' => $report])
</x-app-layout>
