@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-xl border-espresso-200 bg-white text-espresso-900 shadow-sm focus:border-gold-500 focus:ring-gold-500 disabled:bg-cream-100']) }}>
    {{ $slot }}
</select>
