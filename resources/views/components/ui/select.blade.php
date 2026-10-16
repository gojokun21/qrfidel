@aware(['for' => null, 'error' => null, 'hint' => null, 'errorBag' => 'default'])
@props(['invalid' => null])

@php($control = \App\View\FormControl::state($attributes, $invalid, $for, $error, $hint, $errorBag, $errors ?? null))

<select {{ $attributes->class([$control['classes'], 'pr-10'])->merge($control['aria']) }}>
    {{ $slot }}
</select>
