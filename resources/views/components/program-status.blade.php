@props(['status'])

@php($status = $status instanceof \App\Enums\ProgramStatus ? $status : \App\Enums\ProgramStatus::from($status))
<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
    'bg-emerald-100 text-emerald-800' => $status === \App\Enums\ProgramStatus::Active,
    'bg-amber-100 text-amber-800' => $status === \App\Enums\ProgramStatus::Paused,
    'bg-espresso-100 text-espresso-700' => $status === \App\Enums\ProgramStatus::Draft,
    'bg-espresso-50 text-espresso-400' => $status === \App\Enums\ProgramStatus::Archived,
]) }}>{{ $status->label() }}</span>
