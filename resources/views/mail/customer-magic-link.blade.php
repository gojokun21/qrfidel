<x-mail::message>
# Intră în contul tău

@if ($business)
Apasă butonul de mai jos ca să intri în contul tău și să te înscrii în programul de fidelitate **{{ $business->name }}**.
@else
Apasă butonul de mai jos ca să intri în contul tău și să-ți vezi cardurile de fidelitate.
@endif

<x-mail::button :url="$url">
Intră în cont
</x-mail::button>

Linkul e valabil {{ $ttlMinutes }} minute și se poate folosi o singură dată. Dacă nu tu l-ai cerut, poți ignora emailul.

Mulțumim,<br>
{{ config('app.name') }}
</x-mail::message>
