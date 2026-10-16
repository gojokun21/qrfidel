{{-- Detaliul unei afaceri pentru adminul platformei (ADR 014): cine o deține, ce are, cifrele și suspendarea. --}}
@php
    $suspended = $business->status === \App\Enums\BusinessStatus::Suspended;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <a href="{{ route('admin.index') }}" class="link text-sm">← Toate afacerile</a>
                <h2 class="mt-1 flex flex-wrap items-center gap-3 text-2xl font-bold tracking-tight text-espresso-900">
                    {{ $business->name }}
                    @include('admin._status', ['status' => $business->status])
                </h2>
                <p class="mt-1 text-sm text-espresso-500">
                    <span class="font-mono text-xs">/b/{{ $business->slug }}</span> · {{ $business->type->label() }} · {{ $business->timezone }} · creată pe {{ $business->created_at?->format('d.m.Y') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-10">
            <x-flash />

            <div class="grid gap-6 lg:grid-cols-3">
                <x-card>
                    <h3 class="font-semibold text-espresso-900">Owneri</h3>
                    <ul class="mt-3 space-y-1 text-sm text-espresso-700">
                        @forelse ($owners as $owner)
                            <li class="truncate">{{ $owner->name }} · <span class="text-espresso-500">{{ $owner->email }}</span></li>
                        @empty
                            <li class="text-espresso-500">Niciun owner.</li>
                        @endforelse
                    </ul>
                </x-card>
                <x-card>
                    <h3 class="font-semibold text-espresso-900">Locații</h3>
                    <ul class="mt-3 space-y-1 text-sm text-espresso-700">
                        @forelse ($locations as $location)
                            <li class="truncate">{{ $location->name }}@if ($location->address) · <span class="text-espresso-500">{{ $location->address }}</span>@endif</li>
                        @empty
                            <li class="text-espresso-500">Nicio locație.</li>
                        @endforelse
                    </ul>
                </x-card>
                <x-card>
                    <h3 class="font-semibold text-espresso-900">Programe</h3>
                    <ul class="mt-3 space-y-1 text-sm text-espresso-700">
                        @forelse ($programs as $program)
                            <li class="flex items-center justify-between gap-2"><span class="truncate">{{ $program->name }}</span> <x-program-status :status="$program->status" /></li>
                        @empty
                            <li class="text-espresso-500">Niciun program.</li>
                        @endforelse
                    </ul>
                </x-card>
            </div>

            <h3 class="text-lg font-semibold text-espresso-900">{{ $report->periodLabel() }}</h3>
            @include('dashboard.reports._summary', ['report' => $report])

            <x-card>
                @if ($suspended)
                    <h3 class="font-semibold text-espresso-900">Reactivează afacerea</h3>
                    <p class="mt-1 text-sm text-espresso-600">Echipa poate intra din nou în dashboard, iar clienții pot primi ștampile.</p>
                @else
                    <h3 class="font-semibold text-espresso-900">Suspendă afacerea</h3>
                    <p class="mt-1 text-sm text-espresso-600">Echipa nu mai poate intra în dashboard, pagina publică dispare și nu se mai dau ștampile sau recompense. Datele rămân neatinse.</p>
                @endif

                <form method="POST" action="{{ route($suspended ? 'admin.businesses.reactivate' : 'admin.businesses.suspend', $business) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    <div class="min-w-0 flex-1">
                        <x-input-label for="reason" value="Motiv (opțional)" />
                        <x-text-input id="reason" name="reason" class="mt-1 block w-full" maxlength="500" :value="old('reason')" />
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>
                    <button @class(['shrink-0', 'btn-gold' => $suspended, 'btn-danger' => ! $suspended])>
                        {{ $suspended ? 'Reactivează afacerea' : 'Suspendă afacerea' }}
                    </button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
