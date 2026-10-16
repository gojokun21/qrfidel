<x-app-layout :title="$program->name" :back="route('dashboard.programs.index')" back-label="Programe">
    <x-slot:meta>
        <x-loyalty.program-status :status="$program->status" />
    </x-slot:meta>

    @include('dashboard.programs._wizard', ['action' => route('dashboard.programs.update', $program), 'method' => 'PATCH'])
</x-app-layout>
