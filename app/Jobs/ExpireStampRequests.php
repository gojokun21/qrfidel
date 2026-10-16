<?php

namespace App\Jobs;

use App\Services\Loyalty\ExpirationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Cererile de la tejghea rămase pending după termen (ADR 012, 013). Pornit de scheduler; logica stă în ExpirationService.
 */
class ExpireStampRequests implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * O singură copie în coadă; lock-ul expiră după 5 minute, ca o rulare blocată să nu oprească următoarele.
     */
    public int $uniqueFor = 300;

    public function handle(ExpirationService $expirations): void
    {
        $expirations->expireStampRequests();
    }
}
