@props([
    'label' => null,
    'for' => null,
    'error' => null,
    'hint' => null,
    'optional' => false,
    'errorBag' => 'default',
])

{{--
    Un câmp de formular: etichetă, controlul (slot), indiciu și erori.
    `for` = id-ul controlului; `error` = cheia erorii (implicit `for`); `errorBag` pentru formularele Breeze cu bag-uri proprii.
    Controalele x-ui.input / select / textarea din slot citesc aceleași valori (@aware): sunt marcate invalide doar
    când acest câmp are eroare și primesc singure aria-describedby (indiciu + eroare) și aria-invalid.
--}}
@php
    $messages = isset($errors) ? $errors->getBag($errorBag)->get($error ?? $for) : [];
@endphp

<div {{ $attributes }}>
    @if ($label)
        <x-ui.label :for="$for" :value="$label" :optional="$optional" />
    @endif
    <div @class(['mt-1' => $label])>
        {{ $slot }}
    </div>
    @if ($hint)
        <p id="{{ $for }}-hint" class="mt-1.5 text-sm text-ink-muted">{{ $hint }}</p>
    @endif
    <x-ui.error :messages="$messages" :id="$for ? $for.'-error' : null" class="mt-2" />
</div>
