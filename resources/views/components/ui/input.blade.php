@aware(['for' => null, 'error' => null, 'hint' => null, 'errorBag' => 'default'])
@props(['invalid' => null])

{{-- Câmp text. În x-ui.field e marcat invalid când câmpul are eroare (sau cu :invalid explicit). --}}
@php($control = \App\View\FormControl::state($attributes, $invalid, $for, $error, $hint, $errorBag, $errors ?? null))

<input {{ $attributes->class([$control['classes'], 'block w-full'])->merge($control['aria']) }}>
