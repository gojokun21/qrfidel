<?php

namespace App\Services\Loyalty;

use App\Models\Card;
use App\Models\CardTransaction;

final readonly class VoidResult
{
    /**
     * @param  bool  $replayed  true când cheia de idempotency fusese deja folosită și nu s-a scris nimic nou
     */
    public function __construct(
        public Card $card,
        public CardTransaction $voidTransaction,
        public bool $replayed,
    ) {}
}
