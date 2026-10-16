@props(['status'])

{{-- Statusul unui program (ProgramStatus) ca badge. --}}
@php
    $status = $status instanceof \App\Enums\ProgramStatus ? $status : \App\Enums\ProgramStatus::from($status);
    $tone = match ($status) {
        \App\Enums\ProgramStatus::Active => 'success',
        \App\Enums\ProgramStatus::Paused => 'warning',
        \App\Enums\ProgramStatus::Draft => 'neutral',
        \App\Enums\ProgramStatus::Archived => 'muted',
    };
@endphp

<x-ui.badge :tone="$tone" {{ $attributes }}>{{ $status->label() }}</x-ui.badge>
