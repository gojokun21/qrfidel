@props(['status'])

{{-- Statusul unei afaceri (BusinessStatus) ca badge, în panoul de admin. --}}
<x-ui.badge :tone="$status === \App\Enums\BusinessStatus::Suspended ? 'danger' : 'success'" {{ $attributes }}>{{ $status->label() }}</x-ui.badge>
