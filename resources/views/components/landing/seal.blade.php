{{-- Ștampila aurie de pe landing: sigiliu zimțat cu monograma „CL”. `id` separă gradientele când sunt mai multe pe pagină. --}}
@props(['id' => 'seal'])
<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" fill="none" aria-hidden="true" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $id }}-fill" x1="15" y1="5" x2="85" y2="95" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#F5E8C8"/>
            <stop offset=".35" stop-color="#E0B95F"/>
            <stop offset=".7" stop-color="#D9822B"/>
            <stop offset="1" stop-color="#8A6220"/>
        </linearGradient>
        <radialGradient id="{{ $id }}-shine" cx="36" cy="28" r="34" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#FFFFFF" stop-opacity=".6"/>
            <stop offset="1" stop-color="#FFFFFF" stop-opacity="0"/>
        </radialGradient>
    </defs>
    <path d="M50 4 57.5 7.7 65.7 6.8 71.5 12.8 79.6 14.8 82.9 22.4 89.8 27 90.4 35.3 95.3 42 93 50 95.3 58 90.4 64.7 89.8 73 82.9 77.6 79.6 85.2 71.5 87.2 65.7 93.2 57.5 92.3 50 96 42.5 92.3 34.3 93.2 28.5 87.2 20.4 85.2 17.1 77.6 10.2 73 9.6 64.7 4.7 58 7 50 4.7 42 9.6 35.3 10.2 27 17.1 22.4 20.4 14.8 28.5 12.8 34.3 6.8 42.5 7.7Z" fill="url(#{{ $id }}-fill)" stroke="#AE7E25" stroke-width="1.5" stroke-linejoin="round"/>
    <circle cx="50" cy="50" r="36" stroke="#6B4B1D" stroke-opacity=".55" stroke-width="1.5"/>
    <circle cx="50" cy="50" r="31" stroke="#6B4B1D" stroke-opacity=".45" stroke-width="1.5" stroke-dasharray="3 3.5"/>
    <g transform="translate(26 26)" stroke="#2F2622" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22.2 18.6a6.4 6.4 0 1 0 0 10.8"/>
        <path d="M25.6 17.2v13.6h6"/>
    </g>
    <ellipse cx="38" cy="30" rx="24" ry="16" fill="url(#{{ $id }}-shine)"/>
</svg>
