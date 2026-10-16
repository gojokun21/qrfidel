@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
])

{{--
    Butonul aplicației. Cu `href` devine link (<a>), altfel <button> (implicit type="submit").
    variant: primary (acțiunea principală) · neutral (închis) · secondary (contur) · ghost (acțiune discretă în rând)
             · danger · danger-ghost (acțiune distructivă în rând) · inverse (contur pe fundal închis)
    size: sm · md · lg · xl. `icon` = numele unei iconițe x-ui.icon, afișată înaintea textului.
    Stări: :disabled / disabled (și pentru loading, cu Alpine: `:disabled="sending"`).
--}}
@php
    $variants = [
        'primary' => 'bg-primary text-primary-fg shadow-card hover:bg-primary-hover active:bg-primary-active',
        'neutral' => 'bg-inverse text-inverse-fg hover:bg-inverse-line active:bg-inverse-deep',
        'secondary' => 'border border-line-strong bg-surface text-ink hover:border-ink-muted hover:bg-canvas',
        'ghost' => 'text-ink-secondary hover:bg-surface-muted hover:text-ink',
        'danger' => 'bg-danger text-danger-fg hover:bg-danger-hover',
        'danger-ghost' => 'text-danger hover:bg-danger-soft hover:text-danger-hover',
        'inverse' => 'border border-inverse-fg/30 text-inverse-fg hover:border-primary hover:text-primary-hover',
    ];
    $sizes = [
        'sm' => 'gap-1.5 px-2.5 py-1.5 text-sm',
        'md' => 'gap-2 px-4 py-2.5 text-sm',
        'lg' => 'gap-2 px-6 py-3.5 text-base',
        'xl' => 'gap-2.5 px-8 py-4 text-lg',
    ];
    $iconSizes = ['sm' => 'h-4 w-4', 'md' => 'h-4 w-4', 'lg' => 'h-5 w-5', 'xl' => 'h-5 w-5'];

    $classes = 'inline-flex items-center justify-center rounded-control font-semibold transition duration-150 ease-out '
        .'focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 '
        .'disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 '
        .$variants[$variant].' '.$sizes[$size];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="{{ $iconSizes[$size] }} shrink-0" />@endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->class($classes)->merge(['type' => 'submit']) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="{{ $iconSizes[$size] }} shrink-0" />@endif
        {{ $slot }}
    </button>
@endif
