@props(['dark' => false])

{{-- Logo + numele platformei. `dark` = pe fundal închis. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <x-brand.logo class="h-9 w-9 shrink-0 text-primary" />
    <span @class(['text-lg font-bold leading-none tracking-tight', 'text-inverse-fg' => $dark, 'text-ink' => ! $dark])>Creative <span class="text-primary">Loyalty</span></span>
</span>
