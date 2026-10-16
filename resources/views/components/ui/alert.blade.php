@props(['tone' => 'info', 'title' => null])

{{--
    Mesaj de stare în pagină. tone: info · success · warning · danger.
    Are iconiță și rol ARIA (danger = alert, celelalte = status), deci nu depinde doar de culoare.
--}}
@php
    $styles = [
        'info' => 'border-line bg-surface-muted text-ink',
        'success' => 'border-success-line bg-success-soft text-success-ink',
        'warning' => 'border-warning-line bg-warning-soft text-warning-ink',
        'danger' => 'border-danger-line bg-danger-soft text-danger-ink',
    ];
    $icons = [
        'info' => 'M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-11.25a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9.25 9.5a.75.75 0 0 1 1.5 0v4a.75.75 0 0 1-1.5 0v-4Z',
        'success' => 'M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.86-9.86a.75.75 0 0 0-1.22-.88l-3.24 4.5-1.6-1.6a.75.75 0 1 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.09l3.73-5.24Z',
        'warning' => 'M8.48 2.95a1.75 1.75 0 0 1 3.04 0l6.28 10.92A1.75 1.75 0 0 1 16.28 16.5H3.72a1.75 1.75 0 0 1-1.52-2.63L8.48 2.95ZM10 6.5a.75.75 0 0 0-.75.75v3.5a.75.75 0 0 0 1.5 0v-3.5A.75.75 0 0 0 10 6.5Zm0 7.75a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z',
        'danger' => 'M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm-.75-11.5a.75.75 0 0 1 1.5 0v4a.75.75 0 0 1-1.5 0v-4ZM10 14.75a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z',
    ];
@endphp

<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->class(['flex items-start gap-3 rounded-card border p-4 text-sm', $styles[$tone]]) }}>
    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="{{ $icons[$tone] }}"/></svg>
    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-1' => $title])>{{ $slot }}</div>
    </div>
</div>
