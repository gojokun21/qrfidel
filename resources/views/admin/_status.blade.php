{{-- Eticheta statusului unei afaceri în panoul de admin. --}}
<span @class([
    'inline-flex w-fit items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
    'bg-emerald-50 text-emerald-800' => $status === \App\Enums\BusinessStatus::Active,
    'bg-red-50 text-red-800' => $status === \App\Enums\BusinessStatus::Suspended,
])>{{ $status->label() }}</span>
