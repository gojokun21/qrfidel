{{--
    Mesajul de succes (`status`) și refuzurile regulilor de business (eroarea `loyalty`). Layout-ul aplicației îl afișează singur.
    Codurile de stare ale Breeze (`profile-updated` etc.) nu sunt mesaje: formularele de profil le afișează singure.
--}}
@php
    $status = session('status');
    $breezeCodes = ['profile-updated', 'password-updated', 'verification-link-sent'];
@endphp

@if (is_string($status) && ! in_array($status, $breezeCodes, true))
    <x-ui.alert tone="success" {{ $attributes }}>{{ $status }}</x-ui.alert>
@endif
@error('loyalty')
    <x-ui.alert tone="danger" {{ $attributes }}>{{ $message }}</x-ui.alert>
@enderror
