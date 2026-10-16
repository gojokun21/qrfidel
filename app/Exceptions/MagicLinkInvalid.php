<?php

namespace App\Exceptions;

/**
 * Magic link-ul clientului nu mai poate fi folosit: expirat, alterat sau deja folosit (ADR 009).
 * Clientul vede o pagină cu un singur pas de urmat: cere alt link.
 */
class MagicLinkInvalid extends LoyaltyException
{
    public function __construct()
    {
        parent::__construct('Linkul de conectare a expirat sau a fost deja folosit. Cere unul nou.');
    }
}
