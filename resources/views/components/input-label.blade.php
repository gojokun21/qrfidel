@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-espresso-700']) }}>
    {{ $value ?? $slot }}
</label>
