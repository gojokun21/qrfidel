{{-- „Jurnal de activitate” (owner, ADR 013): cine, ce, când și de unde (SDD §6). --}}
@php
    $timezone = app(\App\Support\Tenancy\CurrentBusiness::class)->get()?->timezone ?? config('app.timezone');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-espresso-900">Jurnal de activitate</h2>
            <p class="mt-1 text-sm text-espresso-500">Tot ce s-a făcut în afacere: ștampile, recompense, anulări, cereri, echipă și setări.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-10">
            <nav class="flex flex-wrap gap-2" aria-label="Categorii">
                <a href="{{ route('dashboard.audit.index') }}"
                   @class(['rounded-full px-3 py-1 text-sm font-medium', 'bg-espresso-900 text-white' => $category === null, 'bg-cream-100 text-espresso-700 hover:bg-cream-200' => $category !== null])
                   @if ($category === null) aria-current="page" @endif>Toate</a>
                @foreach ($categories as $key => $label)
                    <a href="{{ route('dashboard.audit.index', ['category' => $key]) }}"
                       @class(['rounded-full px-3 py-1 text-sm font-medium', 'bg-espresso-900 text-white' => $category === $key, 'bg-cream-100 text-espresso-700 hover:bg-cream-200' => $category !== $key])
                       @if ($category === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>

            <x-card flush>
                @forelse ($logs as $log)
                    @php
                        $actor = $log->actor;
                        $actorName = match (true) {
                            $actor instanceof \App\Models\User => $actor->name,
                            $actor instanceof \App\Models\Customer => $actor->maskedEmail(),
                            $log->actor_id === null => 'Sistem',
                            default => 'Cont șters',
                        };
                    @endphp
                    <div class="flex flex-col gap-1 p-5 sm:flex-row sm:items-center sm:justify-between {{ $loop->first ? '' : 'border-t border-espresso-100' }}">
                        <div class="min-w-0">
                            <p class="font-semibold text-espresso-900">{{ \App\Support\AuditActions::label($log->action) }}</p>
                            <p class="truncate text-sm text-espresso-600">
                                {{ $actorName }}@if ($log->ip_address) · <span class="font-mono text-xs">{{ $log->ip_address }}</span>@endif
                            </p>
                        </div>
                        <time class="shrink-0 text-xs text-espresso-500" datetime="{{ $log->created_at?->toIso8601String() }}">
                            {{ $log->created_at?->setTimezone($timezone)->format('d.m.Y H:i') }}
                        </time>
                    </div>
                @empty
                    <p class="p-6 text-espresso-500">Nicio activitate.</p>
                @endforelse
            </x-card>

            {{ $logs->links() }}
        </div>
    </div>
</x-app-layout>
