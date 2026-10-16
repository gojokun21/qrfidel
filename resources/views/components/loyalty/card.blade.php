@props([
    'business',
    'name',
    'required',
    'reward',
    'color' => \App\Models\Program::DEFAULT_COLOR,
    'icon' => 'star',
    'filled' => null,
    'live' => false,
    'compact' => false,
])

{{--
    Cardul de fidelitate al clientului: ștampile, progres „X din N”, recompensă.
    Cu `live`, textul și ștampilele urmăresc obiectul Alpine `preview` din părinte (wizard-ul de programe);
    valorile din props sunt randarea inițială (și cea fără JavaScript). `compact` = varianta din liste.
    Culoarea programului vine din date (inline), nu din tokenuri: fiecare afacere își alege culoarea.
--}}
@php
    $required = max(1, (int) $required);
    $filled = $filled ?? max(0, min(3, $required - 1));
    $ink = \App\Support\Color::readableOn($color);
@endphp

<div {{ $attributes->class(['relative overflow-hidden rounded-panel bg-gradient-to-br from-inverse-raised to-inverse-deep text-inverse-fg shadow-overlay', 'p-4' => $compact, 'p-6' => ! $compact]) }}
     role="img" aria-label="Card de fidelitate: {{ $filled }} din {{ $required }} ștampile"
     @if ($live) :aria-label="`Card de fidelitate: ${preview.filled} din ${preview.required} ștampile`" @endif>
    <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full opacity-25 blur-2xl"
         style="background-color: {{ $color }}" @if ($live) :style="{ backgroundColor: preview.color }" @endif></div>

    <div class="relative flex items-center gap-3">
        @if ($live)
            <x-ui.avatar :name="$business" :color="$color" x-bind:style="{ backgroundColor: preview.color, color: preview.ink }" />
        @else
            <x-ui.avatar :name="$business" :color="$color" />
        @endif
        <div class="min-w-0">
            <p class="truncate font-semibold">{{ $business }}</p>
            <p class="truncate text-xs text-inverse-muted" @if ($live) x-text="preview.name || 'Numele programului'" @endif>{{ $name }}</p>
        </div>
    </div>

    @if ($live)
        <div class="relative mt-6 grid gap-2.5" :class="preview.required > 10 ? 'grid-cols-10' : 'grid-cols-5'">
            <template x-for="i in preview.required" :key="i">
                <div class="flex aspect-square items-center justify-center rounded-full transition-colors"
                     :class="i <= preview.filled ? 'shadow-md' : 'border-2 border-dashed border-ink-muted'"
                     :style="i <= preview.filled ? { backgroundColor: preview.color, color: preview.ink } : {}">
                    {{-- Excepție: SVG-ul vine din x-html, deci dimensiunea se pune pe copil cu un variant arbitrar. --}}
                    <span class="flex h-1/2 w-1/2 items-center justify-center [&>svg]:h-full [&>svg]:w-full" x-show="i <= preview.filled" x-html="preview.iconSvg"></span>
                </div>
            </template>
        </div>
    @else
        <div @class(['relative grid', 'mt-6 gap-2.5' => ! $compact, 'mt-4 gap-2' => $compact, 'grid-cols-10' => $required > 10, 'grid-cols-5' => $required <= 10])>
            @for ($i = 1; $i <= $required; $i++)
                @if ($i <= $filled)
                    <div class="flex aspect-square items-center justify-center rounded-full shadow-md" style="background-color: {{ $color }}; color: {{ $ink }}">
                        <x-ui.icon :name="$icon" class="h-1/2 w-1/2" />
                    </div>
                @else
                    <div class="aspect-square rounded-full border-2 border-dashed border-ink-muted"></div>
                @endif
            @endfor
        </div>
    @endif

    <div class="relative mt-5 flex items-end justify-between gap-3">
        <p @class(['font-bold tracking-tight', 'text-2xl' => $compact, 'text-3xl' => ! $compact])>
            <span @if ($live) x-text="preview.filled" @endif>{{ $filled }}</span><span class="text-lg font-semibold text-inverse-muted"> din <span @if ($live) x-text="preview.required" @endif>{{ $required }}</span></span>
        </p>
        <p class="min-w-0 truncate text-right text-sm font-semibold" style="color: {{ $color }}" @if ($live) :style="{ color: preview.color }" x-text="preview.reward || 'Recompensa'" @endif>{{ $reward }}</p>
    </div>
    <div class="relative mt-3 h-2 overflow-hidden rounded-full bg-inverse-line">
        <div class="h-full rounded-full transition-all duration-500"
             style="width: {{ round($filled / $required * 100) }}%; background-color: {{ $color }}"
             @if ($live) :style="{ width: (preview.filled / preview.required * 100) + '%', backgroundColor: preview.color }" @endif></div>
    </div>
</div>
