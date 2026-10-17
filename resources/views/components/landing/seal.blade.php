{{-- Ștampila verde de pe landing: sigiliu zimțat cu steluța din logo. `id` separă gradientele când sunt mai multe pe pagină. --}}
@props(['id' => 'seal'])
<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" fill="none" aria-hidden="true" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $id }}-fill" x1="15" y1="5" x2="85" y2="95" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#9BE0BD"/>
            <stop offset=".35" stop-color="#2DBB7B"/>
            <stop offset=".7" stop-color="#01A760"/>
            <stop offset="1" stop-color="#036A3E"/>
        </linearGradient>
        <radialGradient id="{{ $id }}-shine" cx="36" cy="28" r="34" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#FFFFFF" stop-opacity=".6"/>
            <stop offset="1" stop-color="#FFFFFF" stop-opacity="0"/>
        </radialGradient>
    </defs>
    <path d="M50 4 57.5 7.7 65.7 6.8 71.5 12.8 79.6 14.8 82.9 22.4 89.8 27 90.4 35.3 95.3 42 93 50 95.3 58 90.4 64.7 89.8 73 82.9 77.6 79.6 85.2 71.5 87.2 65.7 93.2 57.5 92.3 50 96 42.5 92.3 34.3 93.2 28.5 87.2 20.4 85.2 17.1 77.6 10.2 73 9.6 64.7 4.7 58 7 50 4.7 42 9.6 35.3 10.2 27 17.1 22.4 20.4 14.8 28.5 12.8 34.3 6.8 42.5 7.7Z" fill="url(#{{ $id }}-fill)" stroke="#036A3E" stroke-width="1.5" stroke-linejoin="round"/>
    <circle cx="50" cy="50" r="36" stroke="#012156" stroke-opacity=".35" stroke-width="1.5"/>
    <circle cx="50" cy="50" r="31" stroke="#012156" stroke-opacity=".3" stroke-width="1.5" stroke-dasharray="3 3.5"/>
    <path d="M50 30c3 9.5 5.5 12.5 16 20c-10.5 7.5-13 10.5-16 20c-3-9.5-5.5-12.5-16-20c10.5-7.5 13-10.5 16-20Z" fill="#FFFFFF"/>
    <ellipse cx="38" cy="30" rx="24" ry="16" fill="url(#{{ $id }}-shine)"/>
</svg>
