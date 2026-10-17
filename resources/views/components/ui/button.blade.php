@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'pill' => false,
])

{{--
    Butonul aplicației. Cu `href` devine link (<a>), altfel <button> (implicit type="submit").
    variant: primary (acțiunea principală) · neutral (închis) · secondary (contur) · ghost (acțiune discretă în rând)
             · danger · danger-ghost (acțiune distructivă în rând) · inverse (contur pe fundal închis)
             · brand (gradient verde, CTA-urile din paginile publice) · light (alb cu contur, pe fundalul pastel)
    size: sm · md · lg · xl. `icon` = numele unei iconițe x-ui.icon, afișată înaintea textului.
    pill: colțuri complet rotunjite (paginile publice), în loc de raza `control`.
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
        'inverse' => 'border border-inverse-fg/30 text-inverse-fg hover:border-accent hover:text-accent-hover',
        // Gradientul pornește de la jade-600: textul alb are cel puțin 5:1 pe toată suprafața.
        'brand' => 'bg-gradient-to-b from-jade-600 to-jade-700 text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.25),0_8px_20px_-6px_rgb(3_122_71/0.55)] hover:from-jade-700 hover:to-jade-800',
        'light' => 'border border-line bg-white text-ink shadow-soft hover:border-line-strong hover:bg-surface-muted',
    ];
    $sizes = [
        'sm' => 'gap-1.5 px-2.5 py-1.5 text-sm',
        'md' => 'gap-2 px-4 py-2.5 text-sm',
        'lg' => 'gap-2 px-6 py-3.5 text-base',
        'xl' => 'gap-2.5 px-8 py-4 text-lg',
    ];
    $iconSizes = ['sm' => 'h-4 w-4', 'md' => 'h-4 w-4', 'lg' => 'h-5 w-5', 'xl' => 'h-5 w-5'];

    $classes = 'inline-flex items-center justify-center '.($pill ? 'rounded-full' : 'rounded-control').' font-semibold transition duration-150 ease-out '
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
