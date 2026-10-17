{{-- Head comun pentru layout-uri. `$title` opțional; `$themeColor` = culoarea barei browserului (implicit albul paginilor publice). --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $themeColor ?? '#FFFFFF' }}">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/icons/icon.svg" type="image/svg+xml">

<title>{{ isset($title) && $title !== '' ? $title.' · ' : '' }}{{ config('app.name', 'LoyUp') }}</title>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=onest:400,500,600,700,800&display=swap" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
