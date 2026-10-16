@props(['name', 'size' => 'md', 'color' => null])

{{--
    Inițiala unui nume (afacere, membru) într-un pătrat rotunjit. Decorativă: numele apare oricum în text.
    `color` = culoare hex (brandul afacerii); textul își alege singur contrastul. Fără `color` → auriul brandului.
--}}
@php
    $sizes = ['sm' => 'h-7 w-7 rounded-lg text-xs', 'md' => 'h-10 w-10 rounded-control text-base', 'lg' => 'h-14 w-14 rounded-card text-2xl'];
@endphp

<span aria-hidden="true" {{ $attributes->class(['flex shrink-0 items-center justify-center font-bold', $sizes[$size], 'bg-primary text-primary-fg' => ! $color]) }}
      @if ($color) style="background-color: {{ $color }}; color: {{ \App\Support\Color::readableOn($color) }}" @endif>{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
