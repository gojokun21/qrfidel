{{-- Detaliul unei afaceri pentru adminul platformei (ADR 014): cine o deține, ce are, cifrele și suspendarea. --}}
@php
    $suspended = $business->status === \App\Enums\BusinessStatus::Suspended;
@endphp

<x-app-layout :title="$business->name" :back="route('admin.index')" back-label="Toate afacerile"
              :description="'/b/'.$business->slug.' · '.$business->type->label().' · '.$business->timezone.' · creată pe '.$business->created_at?->format('d.m.Y')">
    <x-slot:meta>
        <x-loyalty.business-status :status="$business->status" />
    </x-slot:meta>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card title="Owneri">
            <ul class="space-y-1 text-sm text-ink-secondary">
                @forelse ($owners as $owner)
                    <li class="break-words">{{ $owner->name }} · <span class="text-ink-muted">{{ $owner->email }}</span></li>
                @empty
                    <li class="text-ink-muted">Niciun owner.</li>
                @endforelse
            </ul>
        </x-ui.card>
        <x-ui.card title="Locații">
            <ul class="space-y-1 text-sm text-ink-secondary">
                @forelse ($locations as $location)
                    <li class="break-words">{{ $location->name }}@if ($location->address) · <span class="text-ink-muted">{{ $location->address }}</span>@endif</li>
                @empty
                    <li class="text-ink-muted">Nicio locație.</li>
                @endforelse
            </ul>
        </x-ui.card>
        <x-ui.card title="Programe">
            <ul class="space-y-2 text-sm text-ink-secondary">
                @forelse ($programs as $program)
                    <li class="flex items-center justify-between gap-2"><span class="min-w-0 break-words">{{ $program->name }}</span> <x-loyalty.program-status :status="$program->status" /></li>
                @empty
                    <li class="text-ink-muted">Niciun program.</li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>

    <h2 class="text-lg font-semibold text-ink">{{ $report->periodLabel() }}</h2>
    @include('dashboard.reports._summary', ['report' => $report])

    <x-ui.card :title="$suspended ? 'Reactivează afacerea' : 'Suspendă afacerea'"
               :description="$suspended
                   ? 'Echipa poate intra din nou în dashboard, iar clienții pot primi ștampile.'
                   : 'Echipa nu mai poate intra în dashboard, pagina publică dispare și nu se mai dau ștampile sau recompense. Datele rămân neatinse.'">
        <form method="POST" action="{{ route($suspended ? 'admin.businesses.reactivate' : 'admin.businesses.suspend', $business) }}" class="flex flex-col gap-3 sm:flex-row sm:items-start">
            @csrf
            <x-ui.field label="Motiv" for="reason" optional class="min-w-0 flex-1">
                <x-ui.input id="reason" name="reason" maxlength="500" :value="old('reason')" />
            </x-ui.field>
            <x-ui.button :variant="$suspended ? 'primary' : 'danger'" class="shrink-0 sm:mt-6">
                {{ $suspended ? 'Reactivează afacerea' : 'Suspendă afacerea' }}
            </x-ui.button>
        </form>
    </x-ui.card>
</x-app-layout>
