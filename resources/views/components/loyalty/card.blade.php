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
    Cardul de fidelitate al clientului, în stilul unui pass de wallet: suprafață plină în culoarea programului,
    afacerea, programul și progresul „X din N” sus (SDD §1), sloturile de ștampile, recompensa jos.
    Sub role="img", aria-label-ul spune tot: programul, progresul și recompensa.
    Cu `live`, textul și ștampilele urmăresc obiectul Alpine `preview` din părinte (wizard-ul de programe);
    valorile din props sunt randarea inițială (și cea fără JavaScript). `compact` = varianta din liste.
    Culoarea programului vine din date (inline), nu din tokenuri: fiecare afacere își alege culoarea.
    Cerneala (`$ink`) e alb sau bleumarin, după contrast; ștampilele pline o inversează (fond = cerneală).
--}}
@php
    $required = max(1, (int) $required);
    $filled = $filled ?? max(0, min(3, $required - 1));
    $ink = \App\Support\Color::readableOn($color);
@endphp

<div {{ $attributes->class(['relative overflow-hidden rounded-panel shadow-float ring-1 ring-inset ring-black/10', 'p-4' => $compact, 'p-5' => ! $compact]) }}
     style="background-color: {{ $color }}; color: {{ $ink }}"
     role="img" aria-label="Card de fidelitate {{ $name }}: {{ $filled }} din {{ $required }} ștampile, recompensa: {{ $reward }}"
     @if ($live)
         :style="{ backgroundColor: preview.color, color: preview.ink }"
         :aria-label="`Card de fidelitate ${preview.name}: ${preview.filled} din ${preview.required} ștampile, recompensa: ${preview.reward}`"
     @endif>
    <div class="flex min-w-0 items-center gap-3">
        <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control text-base font-bold"
              style="background-color: {{ $ink }}; color: {{ $color }}"
              @if ($live) :style="{ backgroundColor: preview.ink, color: preview.color }" @endif>{{ mb_strtoupper(mb_substr($business, 0, 1)) }}</span>
        <div class="min-w-0 flex-1">
            <p class="truncate font-semibold leading-tight">{{ $business }}</p>
            <p class="mt-0.5 truncate text-xs opacity-75" @if ($live) x-text="preview.name || 'Numele programului'" @endif>{{ $name }}</p>
        </div>
        <p class="shrink-0 text-sm font-semibold tabular-nums"
           @if ($live) x-text="`${preview.filled} din ${preview.required}`" @endif>{{ $filled }} din {{ $required }}</p>
    </div>

    <div @class(['h-px bg-current opacity-20', 'my-4' => $compact, 'my-5' => ! $compact])></div>

    @if ($live)
        <div class="grid gap-2.5" :class="preview.required > 10 ? 'grid-cols-10' : 'grid-cols-5'">
            <template x-for="i in preview.required" :key="i">
                <div class="flex aspect-square items-center justify-center rounded-full transition-colors"
                     :class="i <= preview.filled ? '' : 'border-[1.5px] border-current opacity-30'"
                     :style="i <= preview.filled ? { backgroundColor: preview.ink, color: preview.color } : {}">
                    {{-- Excepție: SVG-ul vine din x-html, deci dimensiunea se pune pe copil cu un variant arbitrar. --}}
                    <span class="flex h-1/2 w-1/2 items-center justify-center [&>svg]:h-full [&>svg]:w-full" x-show="i <= preview.filled" x-html="preview.iconSvg"></span>
                </div>
            </template>
        </div>
    @else
        <div @class(['grid', 'gap-2.5' => ! $compact, 'gap-2' => $compact, 'grid-cols-10' => $required > 10, 'grid-cols-5' => $required <= 10])>
            @for ($i = 1; $i <= $required; $i++)
                @if ($i <= $filled)
                    <div class="flex aspect-square items-center justify-center rounded-full" style="background-color: {{ $ink }}; color: {{ $color }}">
                        <x-ui.icon :name="$icon" class="h-1/2 w-1/2" />
                    </div>
                @else
                    <div class="aspect-square rounded-full border-[1.5px] border-current opacity-30"></div>
                @endif
            @endfor
        </div>
    @endif

    <p @class(['flex min-w-0 items-center gap-2 text-sm font-semibold', 'mt-4' => $compact, 'mt-5' => ! $compact])>
        <x-ui.icon name="gift" class="h-4 w-4 shrink-0 opacity-70" />
        <span class="truncate" @if ($live) x-text="preview.reward || 'Recompensa'" @endif>{{ $reward }}</span>
    </p>
</div>
