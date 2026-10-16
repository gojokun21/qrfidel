@php($statuses = \App\Enums\ProgramStatus::class)
<x-app-layout title="Programe">
    @can('create', [\App\Models\Program::class, $business])
        <x-slot:actions>
            <x-ui.button :href="route('dashboard.programs.create')" icon="plus">Program nou</x-ui.button>
        </x-slot:actions>
    @endcan

    @forelse ($programs as $program)
        <x-ui.card @class(['grid gap-6 md:grid-cols-[16rem_minmax(0,1fr)]', 'opacity-70' => $program->status === $statuses::Archived])>
            <x-loyalty.card compact
                            :business="$business->name"
                            :name="$program->name"
                            :required="$program->stamps_required"
                            :reward="$program->reward_title"
                            :color="$program->displayColor()"
                            :icon="$program->stamp_icon->value" />

            <div class="flex min-w-0 flex-col">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="min-w-0 break-words text-lg font-semibold text-ink">{{ $program->name }}</h2>
                    <x-loyalty.program-status :status="$program->status" />
                </div>
                <p class="mt-1 text-sm text-ink-secondary">{{ $program->stamps_required }} ștampile → {{ $program->reward_title }}</p>

                <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-muted">
                    <li>{{ $program->cooldown_minutes > 0 ? 'Pauză '.$program->cooldown_minutes.' min' : 'Fără pauză' }}</li>
                    <li>{{ $program->daily_limit ? 'Maximum '.$program->daily_limit.' pe zi' : 'Fără limită zilnică' }}</li>
                    @if ($program->max_stamps_per_visit > 1)
                        <li>Până la {{ $program->max_stamps_per_visit }} pe vizită</li>
                    @endif
                    <li>{{ $program->reward_expire_days ? 'Recompensa expiră în '.$program->reward_expire_days.' zile' : 'Recompensa nu expiră' }}</li>
                    <li>{{ trans_choice('{0} Niciun card|{1} :count card|[2,19] :count carduri|[20,*] :count de carduri', $program->cards_count) }}</li>
                </ul>

                @can('update', $program)
                    <div class="-ml-2.5 mt-auto flex flex-wrap items-center gap-1 pt-5">
                        @if ($program->status !== $statuses::Archived)
                            <x-ui.button :href="route('dashboard.programs.edit', $program)" variant="ghost" size="sm">Editează</x-ui.button>
                        @endif

                        @foreach ($program->status->transitions() as $target)
                            <form method="POST" action="{{ route('dashboard.programs.status', $program) }}"
                                  x-data @submit="if ({{ $target === $statuses::Archived ? 'true' : 'false' }} && ! confirm('Arhivezi programul? Nu se mai pot da ștampile, iar acțiunea nu se poate anula.')) { $event.preventDefault() } else { setTimeout(() => $el.querySelector('button').disabled = true) }">
                                @csrf
                                <input type="hidden" name="status" value="{{ $target->value }}">
                                <x-ui.button variant="ghost" size="sm">{{ $target->actionLabel($program->status) }}</x-ui.button>
                            </form>
                        @endforeach

                        <form method="POST" action="{{ route('dashboard.programs.duplicate', $program) }}" x-data @submit="setTimeout(() => $el.querySelector('button').disabled = true)">
                            @csrf
                            <x-ui.button variant="ghost" size="sm">Duplică</x-ui.button>
                        </form>

                        @if ($program->status === $statuses::Draft && $program->cards_count === 0)
                            <form method="POST" action="{{ route('dashboard.programs.destroy', $program) }}"
                                  x-data @submit="if (! confirm('Ștergi această ciornă?')) $event.preventDefault()">
                                @csrf
                                @method('DELETE')
                                <x-ui.button variant="danger-ghost" size="sm">Șterge</x-ui.button>
                            </form>
                        @endif
                    </div>
                @endcan
            </div>
        </x-ui.card>
    @empty
        <x-ui.card>
            <x-ui.empty-state icon="gift" title="Niciun program încă">
                Un program spune câte ștampile strânge clientul și ce primește la final. Pornește de la un template și ajustează-l.
                @can('create', [\App\Models\Program::class, $business])
                    <x-slot:actions>
                        <x-ui.button :href="route('dashboard.programs.create')">Creează primul program</x-ui.button>
                    </x-slot:actions>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @endforelse
</x-app-layout>
