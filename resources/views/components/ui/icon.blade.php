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
            <path d="M5.5 9h12v5a5 5 0 0 1-5 5h-2a5 5 0 0 1-5-5V9Z"/><path d="M17.5 10H19a2.5 2.5 0 0 1 0 5h-1.5"/><path d="M9.5 3c-.6.8-.6 1.7 0 2.5M13.5 3c-.6.8-.6 1.7 0 2.5"/><path d="M4.5 21h14"/>
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
        {{-- Iconurile landing-ului (Figma „Landing v3”), cu traseele din design scalate pe grila de 24. --}}
        @case('shield-check')
            <g transform="scale(1.2)"><path d="M10 2.5 4.167 5v4.167c0 3.75 2.5 7.083 5.833 8.333 3.333-1.25 5.833-4.583 5.833-8.333V5L10 2.5Z"/><path d="m7.5 10 1.667 1.667L12.5 8.333"/></g>
            @break
        @case('click')
            <g transform="scale(1.2)"><path d="m7.5 7.5 9.167 3.333-4.167 1.667-1.667 4.167L7.5 7.5Z"/><path d="M4.167 2.5v1.667m0 0V5m0-.833H2.5m1.667 0H5m6.667-.834-1.25 1.25m-7.084 7.084 1.25-1.25"/></g>
            @break
        @case('list-lines')
            <g transform="scale(1.2)"><path d="M7.5 5h9.167M7.5 10h9.167M7.5 15h9.167M3.333 5h.009M3.333 10h.009M3.333 15h.009"/></g>
            @break
        @case('bar-chart')
            <g transform="scale(1.0909)"><path d="M3.667 18.333V9.167m0 9.166h5.5m-5.5 0H1.833m7.334 0V3.667m0 14.666h5.5m0 0v-6.416m0 6.416h5.5"/></g>
            @break
        @case('scan-frame')
            <g transform="scale(1.0909)"><path d="M3.667 7.333V5.5A1.833 1.833 0 0 1 5.5 3.667h1.833m7.334 0H16.5A1.833 1.833 0 0 1 18.333 5.5v1.833m0 7.334V16.5a1.833 1.833 0 0 1-1.833 1.833h-1.833m-7.334 0H5.5A1.833 1.833 0 0 1 3.667 16.5v-1.833M3.667 11h14.666"/></g>
            @break
        @case('smartphone')
            <g transform="scale(1.0909)"><path d="M8.25 1.833h5.5a2.75 2.75 0 0 1 2.75 2.75v12.834a2.75 2.75 0 0 1-2.75 2.75h-5.5a2.75 2.75 0 0 1-2.75-2.75V4.583a2.75 2.75 0 0 1 2.75-2.75Z"/><path d="M10.083 16.5h1.834"/></g>
            @break
        @case('check-bold')
            <g transform="scale(1.8462)"><path d="M2.708 6.771 5.146 9.208l5.146-5.146" stroke-width="2"/></g>
            @break
    @endswitch
</svg>
