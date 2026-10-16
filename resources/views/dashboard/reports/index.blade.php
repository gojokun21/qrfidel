{{-- „Rapoarte” (manager+, SDD §9, ADR 014). --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-espresso-900">Rapoarte</h2>
                <p class="mt-1 text-sm text-espresso-500">
                    {{ $report->periodLabel() }}, de la {{ $report->from->format('d.m.Y') }} ({{ $business->timezone }}). Ștampilele și recompensele anulate nu se numără.
                </p>
            </div>
            @if ($restrictedTo)
                <span class="inline-flex w-fit items-center gap-2 rounded-full bg-cream-100 px-3 py-1 text-xs font-semibold text-espresso-700">
                    <x-icon name="map-pin" class="h-3.5 w-3.5" /> Doar locația {{ $restrictedTo->name }}
                </span>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-10">
            <nav class="flex flex-wrap gap-2" aria-label="Perioada">
                @foreach ($periods as $days)
                    <a href="{{ route('dashboard.reports', ['zile' => $days]) }}"
                       @class(['rounded-full px-3 py-1 text-sm font-medium', 'bg-espresso-900 text-white' => $report->days === $days, 'bg-cream-100 text-espresso-700 hover:bg-cream-200' => $report->days !== $days])
                       @if ($report->days === $days) aria-current="page" @endif>{{ $days }} zile</a>
                @endforeach
            </nav>

            @include('dashboard.reports._summary', ['report' => $report])
        </div>
    </div>
</x-app-layout>
