{{-- „Jurnal de activitate” (owner, ADR 013): cine, ce, când și de unde (SDD §6). --}}
@php
    $timezone = app(\App\Support\Tenancy\CurrentBusiness::class)->get()?->timezone ?? config('app.timezone');
@endphp

<x-app-layout title="Jurnal de activitate">
    <nav class="flex flex-wrap gap-2" aria-label="Categorii">
        <x-ui.filter-link :href="route('dashboard.audit.index')" :active="$category === null">Toate</x-ui.filter-link>
        @foreach ($categories as $key => $label)
            <x-ui.filter-link :href="route('dashboard.audit.index', ['category' => $key])" :active="$category === $key">{{ $label }}</x-ui.filter-link>
        @endforeach
    </nav>

    <x-ui.card flush>
        @if ($logs->isEmpty())
            <x-ui.empty-state icon="shield" title="Nicio activitate." />
        @else
            <ul class="divide-y divide-line">
                @foreach ($logs as $log)
                    @php
                        $actor = $log->actor;
                        $actorName = match (true) {
                            $actor instanceof \App\Models\User => $actor->name,
                            $actor instanceof \App\Models\Customer => $actor->maskedEmail(),
                            $log->actor_id === null => 'Sistem',
                            default => 'Cont șters',
                        };
                    @endphp
                    <li class="flex flex-col gap-1 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">{{ \App\Support\AuditActions::label($log->action) }}</p>
                            <p class="break-words text-sm text-ink-secondary">
                                {{ $actorName }}@if ($log->ip_address) · <span class="tabular-nums text-xs">{{ $log->ip_address }}</span>@endif
                            </p>
                        </div>
                        <time class="shrink-0 text-xs text-ink-muted" datetime="{{ $log->created_at?->toIso8601String() }}">
                            {{ $log->created_at?->setTimezone($timezone)->format('d.m.Y H:i') }}
                        </time>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{ $logs->links() }}
</x-app-layout>
