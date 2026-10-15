<?php

namespace App\Services\Loyalty;

use Carbon\CarbonImmutable;

/**
 * Un QR emis pentru card: tokenul semnat (conținutul QR-ului) și codul scurt pentru introducere manuală.
 */
final readonly class QrToken
{
    public function __construct(
        public string $token,
        public string $shortCode,
        public CarbonImmutable $issuedAt,
        public CarbonImmutable $expiresAt,
    ) {}
}
