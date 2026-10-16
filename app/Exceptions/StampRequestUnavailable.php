<?php

namespace App\Exceptions;

/**
 * Cererea de ștampilă nu mai poate fi aprobată (expirată, deja tratată) sau clientul a trimis prea multe cereri.
 */
class StampRequestUnavailable extends LoyaltyException
{
    public static function expired(): self
    {
        return new self('Cererea a expirat – clientul poate trimite alta.');
    }

    public static function alreadyHandled(): self
    {
        return new self('Cererea a fost deja tratată.');
    }

    public static function tooMany(int $seconds): self
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return new self("Ai trimis prea multe cereri pentru acest card. Încearcă din nou în {$minutes} min.");
    }
}
