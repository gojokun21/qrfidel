<?php

namespace App\Exceptions;

/**
 * Angajatul fără locație fixă nu a ales încă locația în care scanează (ADR 011).
 */
class StaffLocationMissing extends LoyaltyException
{
    public function __construct()
    {
        parent::__construct('Alege locația în care lucrezi.');
    }
}
