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
        'store' => env('LOYALTY_QR_STORE', 'loyalty'),
    ],

    /*
    | Scanarea la casă (ADR 011): resolve() dă un tichet de scanare valabil ticket_ttl_seconds, legat de card,
    | angajat și locație; un tichet permite o ștampilare și o folosire de recompensă. Rate limit pe resolve:
    | per_minute scanări per angajat (SDD §6).
    */
    'scan' => [
        'ticket_ttl_seconds' => 300,
        'per_minute' => 30,
        'store' => env('LOYALTY_SCAN_STORE', 'loyalty'),
    ],

    /*
    | Cererile de ștampilă de la tejghea (SDD §5.3, §6, ADR 012): o cerere pending expiră după ttl_minutes;
    | per_hour cereri noi per card; panoul angajatului și cardul clientului se reîmprospătează la poll_seconds.
    */
    'requests' => [
        'ttl_minutes' => 10,
        'per_hour' => 3,
        'poll_seconds' => 5,
    ],

    /*
    | Limitatorii numiți (SDD §6, ADR 013), definiți în AppServiceProvider. Cheia e per angajat, client,
    | afacere sau IP – niciodată comună pe IP pentru toate rutele, ca la `throttle:N,1`.
    */
    'rate_limits' => [
        'staff_actions_per_minute' => 60,
        'customer_actions_per_minute' => 20,
        'customer_qr_per_minute' => 10,
        'customer_status_per_minute' => 30,
        'magic_link_per_minute' => 10,
        'invitation_register_per_minute' => 6,
        'invitations_per_hour' => 20,
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
