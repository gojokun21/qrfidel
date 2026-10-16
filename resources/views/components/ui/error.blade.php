@props(['messages' => [], 'id' => null])

{{-- Erorile unui câmp: text + iconiță (nu doar culoare). `id` e legat de câmp prin aria-describedby. --}}
@if ($messages)
    <ul @if ($id) id="{{ $id }}" @endif {{ $attributes->class('space-y-1 text-sm text-danger') }}>
        @foreach ((array) $messages as $message)
            <li class="flex items-start gap-1.5">
                <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm-.75-11.5a.75.75 0 0 1 1.5 0v4a.75.75 0 0 1-1.5 0v-4ZM10 14.75a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                <span>{{ $message }}</span>
            </li>
        @endforeach
    </ul>
@endif
