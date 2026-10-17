@props(['dark' => false])

{{-- Logo-ul LoyUp.ro (simbol + nume), din fișierul brandului. `dark` = pe fundal închis: numele devine alb.
     Fără `class` are înălțimea h-10; cine dă `class` își alege și înălțimea. --}}
<img src="{{ asset($dark ? 'images/brand/loyup-white.png' : 'images/brand/loyup.png') }}" alt="LoyUp.ro" width="501" height="144"
     {{ $attributes->class(['h-10' => ! $attributes->has('class'), 'w-auto']) }}>
