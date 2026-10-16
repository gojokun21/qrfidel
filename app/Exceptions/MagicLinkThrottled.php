<?php

namespace App\Exceptions;

/**
 * Prea multe magic link-uri cerute pentru același email (SDD §6: 5 pe oră).
 */
class MagicLinkThrottled extends LoyaltyException
{
    public function __construct(int $seconds)
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        parent::__construct("Ți-am trimis deja mai multe linkuri. Verifică-ți emailul sau încearcă din nou peste {$minutes} min.");
    }
}
