@props(['dark' => false])

{{-- Logo-ul LoyUp.ro (simbol + nume), din fișierul brandului. `dark` = pe fundal închis: numele devine alb. --}}
<img src="{{ asset($dark ? 'images/brand/loyup-white.png' : 'images/brand/loyup.png') }}" alt="LoyUp.ro" width="501" height="144"
     {{ $attributes->merge(['class' => 'h-10 w-auto']) }}>
