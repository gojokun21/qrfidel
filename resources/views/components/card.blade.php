@props(['flush' => false])

{{-- `flush` = fără padding, pentru liste cu rânduri care își pun singure padding-ul. --}}
<div {{ $attributes->class(['rounded-2xl border border-espresso-100 bg-white shadow-sm', 'p-6' => ! $flush, 'overflow-hidden' => $flush]) }}>
    {{ $slot }}
</div>
