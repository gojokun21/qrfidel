<?php

namespace App\Exceptions;

/**
 * Acțiunea de staff nu are o scanare valabilă în spate (ADR 011): tichet expirat, falsificat, al altui card
 * sau al altui angajat, ori deja folosit pentru acțiunea cerută.
 */
class InvalidScanTicket extends LoyaltyException
{
    public static function invalid(): self
    {
        return new self('Scanează din nou codul clientului.');
    }

    public static function alreadyUsed(): self
    {
        return new self('Această scanare a fost deja folosită – scanează din nou codul clientului.');
    }
}
