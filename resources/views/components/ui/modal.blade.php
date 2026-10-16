@props([
    'name',
    'title',
    'show' => false,
    'maxWidth' => 'lg',
])

{{--
    Dialog modal. Se deschide cu `$dispatch('open-modal', '{name}')` și se închide cu `close-modal`, Escape,
    click pe fundal sau `$dispatch('close')` din interior.
    Accesibilitate: role="dialog" + aria-modal, titlul legat prin aria-labelledby, focusul intră pe primul
    element focusabil, Tab rămâne în dialog, iar la închidere focusul revine pe elementul care l-a deschis.
--}}
@php
    $maxWidth = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'][$maxWidth];
    $panelId = 'modal-'.$name;
    $titleId = $panelId.'-title';
@endphp

<div
    x-data="{
        show: @js($show),
        trigger: null,
        focusables() {
            const selector = 'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex=\'-1\'])';
            return [...$refs.panel.querySelectorAll(selector)];
        },
        open() {
            this.trigger = document.activeElement;
            this.show = true;
        },
        close() {
            this.show = false;
        },
        trap(event) {
            const items = this.focusables();
            if (items.length === 0) { event.preventDefault(); return; }
            const first = items[0], last = items[items.length - 1], active = document.activeElement;
            if (event.shiftKey && (active === first || active === $refs.panel)) { last.focus(); event.preventDefault(); }
            else if (! event.shiftKey && (active === last || active === $refs.panel)) { first.focus(); event.preventDefault(); }
        },
        {{-- Deschis la încărcare (de ex. după o eroare de validare): focusul revine pe butonul cu aria-controls spre dialog. --}}
        returnFocus() {
            (this.trigger ?? document.querySelector(@js('[aria-controls="'.$panelId.'"]')))?.focus();
        },
    }"
    x-init="$watch('show', value => {
        document.body.classList.toggle('overflow-hidden', value);
        if (value) {
            $nextTick(() => (focusables()[0] ?? $refs.panel).focus());
        } else {
            returnFocus();
        }
    }); if (show) { document.body.classList.add('overflow-hidden'); $nextTick(() => (focusables()[0] ?? $refs.panel).focus()) }"
    x-on:open-modal.window="$event.detail === @js($name) && open()"
    x-on:close-modal.window="$event.detail === @js($name) && close()"
    x-on:close.stop="close()"
    x-on:keydown.escape.window="show && close()"
    x-show="show"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    <div x-show="show" class="fixed inset-0 bg-inverse-deep/60" x-on:click="close()" aria-hidden="true"
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

    <div x-show="show" x-ref="panel" id="{{ $panelId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $titleId }}" tabindex="-1"
         x-on:keydown.tab="trap($event)"
         class="relative mx-auto mt-16 w-full sm:mt-24 overflow-hidden rounded-card bg-surface shadow-overlay focus:outline-none sm:w-full {{ $maxWidth }}"
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        <div class="p-6">
            <h2 id="{{ $titleId }}" class="text-lg font-semibold text-ink">{{ $title }}</h2>
            <div class="mt-2">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
