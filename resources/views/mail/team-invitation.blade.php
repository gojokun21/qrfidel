<x-mail::message>
# Ai fost invitat în echipa {{ $business->name }}

Ai fost invitat să te alături echipei **{{ $business->name }}** pe Creative Loyalty, cu rolul **{{ $role }}**.

<x-mail::button :url="$url">
Acceptă invitația
</x-mail::button>

Linkul e valabil până pe {{ $expiresAt }}. Dacă nu te așteptai la această invitație, poți ignora emailul.

Mulțumim,<br>
{{ config('app.name') }}
</x-mail::message>
