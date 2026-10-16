<?php

return [

    /*
    | QR-ul rotativ al cardului (SDD §5.2, §6, ADR 005): emis de server, valabil ttl_seconds,
    | de unică folosință (jti păstrat used_ttl_seconds în store-ul dat, Redis în producție;
    | minim ttl_seconds + clock_skew_seconds). PWA-ul cere un QR nou la refresh_seconds (ADR 010).
    */
    'qr' => [
        'ttl_seconds' => 90,
        'refresh_seconds' => 30,
        // Codul scurt anterior rămâne valabil atâtea secunde după rotație (clientul îl poate dicta chiar atunci).
        'short_code_grace_seconds' => 20,
        'used_ttl_seconds' => 300,
        'clock_skew_seconds' => 5,
        'store' => env('LOYALTY_QR_STORE', 'redis'),
    ],

    /*
    | Magic link-ul clientului (SDD §5.1, §6, ADR 009): URL semnat valabil ttl_minutes,
    | de unică folosință (nonce consumat în store-ul dat, separat de cache-ul golit de cache:clear).
    | Rate limit: per_email_per_hour per email + IP, per_email_per_hour_all_ips per email din orice IP.
    */
    'magic_link' => [
        'ttl_minutes' => 15,
        'per_email_per_hour' => 5,
        'per_email_per_hour_all_ips' => 20,
        'store' => env('LOYALTY_MAGIC_LINK_STORE', 'loyalty'),
    ],

];
