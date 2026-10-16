@props(['dark' => false])

{{-- Logo + numele platformei. `dark` = pe fundal închis. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <x-application-logo class="h-9 w-9 shrink-0 text-gold-500" />
    <span class="text-lg font-bold leading-none tracking-tight {{ $dark ? 'text-cream-50' : 'text-espresso-900' }}">Creative <span class="text-gold-500">Loyalty</span></span>
</span>
