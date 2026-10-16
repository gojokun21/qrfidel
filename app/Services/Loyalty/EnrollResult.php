<?php

namespace App\Services\Loyalty;

use App\Models\Card;

/**
 * Rezultatul înscrierii: cardul clientului și dacă a fost creat acum (false = era deja înscris).
 */
final readonly class EnrollResult
{
    public function __construct(
        public Card $card,
        public bool $created,
    ) {}
}
