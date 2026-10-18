@props(['href', 'variant' => 'green', 'size' => 'lg', 'arrow' => false])

{{--
    Butoanele-pastilă din Figma „Landing v3” (doar paginile de marketing).
    variant: green (acțiunea principală) · navy (meniul) · light (alb cu contur) · white (pe fundal verde) · outline (contur alb, pe fundal verde)
    size: lg = 16px, 15/26 padding · sm = 14px, 10/18 padding (meniul). `arrow` = săgeata din dreapta.
--}}
@php
    $variants = [
        'green' => 'bg-jade-600 text-white hover:bg-jade-700',
        'navy' => 'bg-navy-900 text-white hover:bg-navy-800',
        'light' => 'border border-steel-200 bg-white text-navy-900 hover:border-steel-300 hover:bg-steel-50',
        'white' => 'bg-white text-navy-900 hover:bg-jade-50',
        'outline' => 'border border-white/40 text-white hover:border-white hover:bg-white/10',
    ];
    $sizes = [
        'lg' => 'gap-2 px-[1.625rem] py-[0.9375rem] text-base',
        'sm' => 'px-[1.125rem] py-2.5 text-sm',
    ];
@endphp

<a href="{{ $href }}" {{ $attributes->class([
    'inline-flex items-center justify-center whitespace-nowrap rounded-full font-semibold leading-tight transition duration-150',
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2',
    $variants[$variant], $sizes[$size],
]) }}>
    {{ $slot }}
    @if ($arrow)
        <x-ui.icon name="arrow-right" class="h-[1.125rem] w-[1.125rem]" />
    @endif
</a>
