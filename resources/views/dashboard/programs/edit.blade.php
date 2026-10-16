<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('dashboard.programs.index') }}" class="text-sm text-espresso-500 hover:text-espresso-800">← Programe</a>
            <h2 class="mt-1 flex flex-wrap items-center gap-3 text-2xl font-bold tracking-tight text-espresso-900">
                {{ $program->name }}
                <x-program-status :status="$program->status" />
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-10">
            @include('dashboard.programs._wizard', ['action' => route('dashboard.programs.update', $program), 'method' => 'PATCH'])
        </div>
    </div>
</x-app-layout>
