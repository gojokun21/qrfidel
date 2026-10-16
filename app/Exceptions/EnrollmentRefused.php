<?php

namespace App\Exceptions;

/**
 * Înscrierea unui client refuzată (SDD §5.1): programul nu e activ sau afacerea e suspendată.
 */
class EnrollmentRefused extends LoyaltyException
{
    public static function programInactive(): self
    {
        return new self('Programul nu primește înscrieri acum.');
    }

    public static function businessInactive(): self
    {
        return new self('Afacerea nu primește înscrieri acum.');
    }
}
