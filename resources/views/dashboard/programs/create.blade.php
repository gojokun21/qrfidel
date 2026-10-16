<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('dashboard.programs.index') }}" class="text-sm text-espresso-500 hover:text-espresso-800">← Programe</a>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-espresso-900">Program nou</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-10">
            @include('dashboard.programs._wizard', ['action' => route('dashboard.programs.store'), 'method' => 'POST'])
        </div>
    </div>
</x-app-layout>
