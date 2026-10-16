@php($statuses = \App\Enums\ProgramStatus::class)
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold tracking-tight text-espresso-900">Programe</h2>
            @can('create', [\App\Models\Program::class, $business])
                <a href="{{ route('dashboard.programs.create') }}" class="btn-gold"><x-icon name="plus" class="h-4 w-4" /> Program nou</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-10">
            <x-flash />

            @forelse ($programs as $program)
                <x-card @class(['mb-4 grid gap-6 md:grid-cols-[16rem_minmax(0,1fr)]', 'opacity-70' => $program->status === $statuses::Archived])>
                    <x-loyalty-card class="!p-4 [&_p.text-3xl]:text-2xl"
                                    :business="$business->name"
                                    :name="$program->name"
                                    :required="$program->stamps_required"
                                    :reward="$program->reward_title"
                                    :color="$program->displayColor()"
                                    :icon="$program->stamp_icon->value" />

                    <div class="flex min-w-0 flex-col">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="truncate text-lg font-semibold text-espresso-900">{{ $program->name }}</h3>
                            <x-program-status :status="$program->status" />
                        </div>
                        <p class="mt-1 text-sm text-espresso-600">{{ $program->stamps_required }} ștampile → {{ $program->reward_title }}</p>

                        <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-espresso-500">
                            <li>{{ $program->cooldown_minutes > 0 ? 'Pauză '.$program->cooldown_minutes.' min' : 'Fără pauză' }}</li>
                            <li>{{ $program->daily_limit ? 'Maximum '.$program->daily_limit.' pe zi' : 'Fără limită zilnică' }}</li>
                            @if ($program->max_stamps_per_visit > 1)
                                <li>Până la {{ $program->max_stamps_per_visit }} pe vizită</li>
                            @endif
                            <li>{{ $program->reward_expire_days ? 'Recompensa expiră în '.$program->reward_expire_days.' zile' : 'Recompensa nu expiră' }}</li>
                            <li>{{ trans_choice('{0} Niciun card|{1} :count card|[2,19] :count carduri|[20,*] :count de carduri', $program->cards_count) }}</li>
                        </ul>

                        @can('update', $program)
                            <div class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-2 pt-5 text-sm">
                                @if ($program->status !== $statuses::Archived)
                                    <a href="{{ route('dashboard.programs.edit', $program) }}" class="link">Editează</a>
                                @endif

                                @foreach ($program->status->transitions() as $target)
                                    <form method="POST" action="{{ route('dashboard.programs.status', $program) }}"
                                          x-data @submit="if ({{ $target === $statuses::Archived ? 'true' : 'false' }} && ! confirm('Arhivezi programul? Nu se mai pot da ștampile, iar acțiunea nu se poate anula.')) { $event.preventDefault() } else { setTimeout(() => $el.querySelector('button').disabled = true) }">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $target->value }}">
                                        <button class="font-medium text-espresso-600 hover:text-espresso-900 hover:underline">{{ $target->actionLabel($program->status) }}</button>
                                    </form>
                                @endforeach

                                <form method="POST" action="{{ route('dashboard.programs.duplicate', $program) }}" x-data @submit="setTimeout(() => $el.querySelector('button').disabled = true)">
                                    @csrf
                                    <button class="font-medium text-espresso-600 hover:text-espresso-900 hover:underline">Duplică</button>
                                </form>

                                @if ($program->status === $statuses::Draft && $program->cards_count === 0)
                                    <form method="POST" action="{{ route('dashboard.programs.destroy', $program) }}"
                                          x-data @submit="if (! confirm('Ștergi această ciornă?')) $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-600 hover:underline">Șterge</button>
                                    </form>
                                @endif
                            </div>
                        @endcan
                    </div>
                </x-card>
            @empty
                <x-card class="flex flex-col items-center py-12 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gold-50 text-gold-600"><x-icon name="gift" class="h-7 w-7" /></span>
                    <p class="mt-4 text-lg font-semibold text-espresso-900">Niciun program încă</p>
                    <p class="mt-1 max-w-md text-sm text-espresso-500">Un program spune câte ștampile strânge clientul și ce primește la final. Pornește de la un template și ajustează-l.</p>
                    @can('create', [\App\Models\Program::class, $business])
                        <a href="{{ route('dashboard.programs.create') }}" class="btn-gold mt-6">Creează primul program</a>
                    @endcan
                </x-card>
            @endforelse
        </div>
    </div>
</x-app-layout>
