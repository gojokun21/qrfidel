@aware(['for' => null, 'error' => null, 'hint' => null, 'errorBag' => 'default'])
@props(['invalid' => null])

@php($control = \App\View\FormControl::state($attributes, $invalid, $for, $error, $hint, $errorBag, $errors ?? null))

<textarea {{ $attributes->class([$control['classes'], 'block w-full'])->merge($control['aria']) }}>{{ $slot }}</textarea>
