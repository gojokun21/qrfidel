<?php

namespace App\Services\Loyalty;

use App\Models\Location;
use Carbon\CarbonImmutable;

/**
 * Un tichet de scanare valabil (ADR 011): cardul scanat de angajat, locația scanării și acțiunile deja făcute.
 */
final readonly class ScanTicket
{
    public function __construct(
        public string $id,
        public Location $location,
        public CarbonImmutable $expiresAt,
        public bool $stampUsed,
        public bool $redeemUsed,
    ) {}
}
