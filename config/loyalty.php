<?php

return [

    /*
    | QR-ul rotativ al cardului (SDD §5.2, §6, ADR 005): emis de server, valabil ttl_seconds,
    | de unică folosință (jti păstrat used_ttl_seconds în store-ul dat, Redis în producție;
    | minim ttl_seconds + clock_skew_seconds). PWA-ul cere un QR nou la 30 s (Sesiunea 7).
    */
    'qr' => [
        'ttl_seconds' => 90,
        'used_ttl_seconds' => 300,
        'clock_skew_seconds' => 5,
        'store' => env('LOYALTY_QR_STORE', 'redis'),
    ],

];
