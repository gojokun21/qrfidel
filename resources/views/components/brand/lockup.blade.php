@props(['size' => 'md'])

{{-- Logo-ul din Figma „Landing v3”: simbolul LoyUp (verdele din logo) + numele scris în Onest Bold.
     `size`: md = meniul (simbol 24px), lg = footer (simbol 26px). --}}
@php
    $sizes = [
        'md' => ['symbol' => 'h-6 w-[1.6776rem]', 'text' => 'text-[1.425rem] tracking-[-0.02em]', 'gap' => 'gap-[0.6rem]'],
        'lg' => ['symbol' => 'h-[1.625rem] w-[1.8174rem]', 'text' => 'text-[1.544rem] tracking-[-0.02em]', 'gap' => 'gap-[0.65rem]'],
    ][$size];
@endphp

<span {{ $attributes->class(['inline-flex items-center', $sizes['gap']]) }}>
    <x-brand.logo @class([$sizes['symbol'], 'shrink-0 text-jade-500']) />
    <span @class(['font-bold leading-tight text-ink', $sizes['text']])>LoyUp</span>
</span>
