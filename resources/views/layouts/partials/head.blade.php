{{-- Head comun pentru layout-uri. `$title` opțional; `$script` încarcă și fontul script al landing-ului. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#1A1D1B">

<title>{{ isset($title) && $title !== '' ? $title.' · ' : '' }}{{ config('app.name', 'Creative Loyalty') }}</title>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=onest:400,500,600,700,800{{ ($script ?? false) ? '|kaushan-script:400' : '' }}&display=swap" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
