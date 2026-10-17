@props(['name'])

{{-- Iconițe inline (outline, grilă 24px), fără dependențe externe. --}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @switch($name)
        @case('home')
            <path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>
            @break
        @case('map-pin')
            <path d="M12 21s-7-5.6-7-11a7 7 0 0 1 14 0c0 5.4-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>
            @break
        @case('users')
            <circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M18 14.3A6.5 6.5 0 0 1 21.5 20"/>
            @break
        @case('building')
            <path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h2a2 2 0 0 1 2 2v10"/><path d="M8 7h4M8 11h4M8 15h4"/><path d="M2 21h20"/>
            @break
        @case('user')
            <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>
            @break
        @case('logout')
            <path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 16l-4-4 4-4"/><path d="M6 12h10"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('chevron-up-down')
            <path d="m8 9 4-4 4 4"/><path d="m16 15-4 4-4-4"/>
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6"/>
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('x')
            <path d="M6 6l12 12M18 6 6 18"/>
            @break
        @case('arrow-right')
            <path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>
            @break
        @case('qr')
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/>
            @break
        @case('shield')
            <path d="M12 3 4 6v6c0 5 3.4 8.3 8 9 4.6-.7 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('chart')
            <path d="M4 20h16"/><path d="M7 16v-5"/><path d="M12 16V7"/><path d="M17 16v-8"/>
            @break
        @case('globe')
            <circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3Z"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('sliders')
            <path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>
            @break
        @case('list')
            <path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>
            @break
        @case('phone')
            <rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M11 18.5h2"/>
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/>
            @break
        @case('link')
            <path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>
            @break
        {{-- Iconurile ștampilelor (App\Enums\StampIcon). --}}
        @case('coffee')
            <path d="M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V9Z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16"/><path d="M8 3c-.6.8-.6 1.7 0 2.5M12 3c-.6.8-.6 1.7 0 2.5"/><path d="M3 21h14"/>
            @break
        @case('utensils')
            <path d="M6 3v7a2 2 0 0 0 2 2h0a2 2 0 0 0 2-2V3"/><path d="M8 12v9"/><path d="M17 21V3c-2 1-3.5 3.5-3.5 7 0 2 1 3 3.5 3"/>
            @break
        @case('cake')
            <path d="M4 21V13a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8"/><path d="M4 16c1.3 1 2.7 1 4 0s2.7-1 4 0 2.7 1 4 0 2.7-1 4 0"/><path d="M2 21h20"/><path d="M12 11V7"/><path d="M12 4.5c.8-.6.8-1.4 0-2-.8.6-.8 1.4 0 2Z"/>
            @break
        @case('car')
            <path d="M5 16V11l2-5h10l2 5v5"/><path d="M3 16h18v2a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1v-1H7v1a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-2Z"/><path d="M5 11h14"/><circle cx="7.5" cy="13.5" r=".5"/><circle cx="16.5" cy="13.5" r=".5"/>
            @break
        @case('scissors')
            <circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M8.1 8.1 20 20M8.1 15.9 20 4"/>
            @break
        @case('star')
            <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9L12 3Z"/>
            @break
        @case('heart')
            <path d="M12 20s-7.5-4.6-9-9.4C2 7.3 4.2 4.5 7.3 4.5c1.9 0 3.4 1 4.7 2.7 1.3-1.7 2.8-2.7 4.7-2.7 3.1 0 5.3 2.8 4.3 6.1-1.5 4.8-9 9.4-9 9.4Z"/>
            @break
        @case('gift')
            <rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8"/><path d="M12 8v13"/><path d="M12 8C10.5 4.5 7 4 7 6.5S10.5 8 12 8Zm0 0c1.5-3.5 5-4 5-1.5S13.5 8 12 8Z"/>
            @break
        @case('check')
            <path d="m5 12.5 4.5 4.5L19 7.5"/>
            @break
        @case('trending-up')
            <path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>
            @break
        @case('bolt')
            <path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>
            @break
        @case('scan')
            <path d="M4 8V5a1 1 0 0 1 1-1h3"/><path d="M16 4h3a1 1 0 0 1 1 1v3"/><path d="M20 16v3a1 1 0 0 1-1 1h-3"/><path d="M8 20H5a1 1 0 0 1-1-1v-3"/><path d="M4 12h16"/>
            @break
    @endswitch
</svg>
