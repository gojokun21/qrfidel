<x-app-layout title="Program nou" :back="route('dashboard.programs.index')" back-label="Programe">
    @include('dashboard.programs._wizard', ['action' => route('dashboard.programs.store'), 'method' => 'POST'])
</x-app-layout>
