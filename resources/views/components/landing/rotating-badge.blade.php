{{-- Insigna rotundă din hero: textul se rotește lent, monograma rămâne pe loc. --}}
<div {{ $attributes->merge(['class' => 'relative']) }} aria-hidden="true">
    <svg viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg" class="h-full w-full motion-safe:animate-spin-slow">
        <defs>
            <path id="rotating-badge-circle" d="M80 80m-60 0a60 60 0 1 1 120 0a60 60 0 1 1-120 0"/>
        </defs>
        <circle cx="80" cy="80" r="78" fill="#01A760"/>
        <circle cx="80" cy="80" r="72" fill="none" stroke="#012156" stroke-opacity=".25" stroke-width="1"/>
        <text fill="#012156" font-family="Onest, sans-serif" font-size="14" font-weight="800" letter-spacing="2">
            <textPath href="#rotating-badge-circle" textLength="372" lengthAdjust="spacing">FĂRĂ APLICAȚIE ✦ FĂRĂ HÂRTIE ✦ FĂRĂ PAROLĂ ✦</textPath>
        </text>
    </svg>
    <x-brand.logo class="absolute inset-0 m-auto h-[34%] w-[38%] text-navy-900" />
</div>
