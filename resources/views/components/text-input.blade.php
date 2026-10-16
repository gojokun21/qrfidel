@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-xl border-espresso-200 bg-white text-espresso-900 shadow-sm placeholder:text-espresso-300 focus:border-gold-500 focus:ring-gold-500 disabled:bg-cream-100 disabled:text-espresso-400']) }}>
