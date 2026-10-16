<?php

namespace App\Services\Loyalty;

use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Program;
use App\Models\Reward;

final readonly class StampResult
{
    /**
     * @param  list<Reward>  $rewardsIssued  recompensele emise de această ștampilare
     * @param  bool  $replayed  true când cheia de idempotency fusese deja folosită și nu s-a scris nimic nou
     */
    public function __construct(
        public Card $card,
        public CardTransaction $transaction,
        public array $rewardsIssued,
        public bool $replayed,
    ) {}

    /**
     * Mesajul pentru angajat după ștampilare (ecranul cardului și panoul de cereri).
     */
    public function summary(): string
    {
        /** @var Program $program */
        $program = $this->card->program;

        return $this->rewardsIssued === []
            ? "Ștampilă adăugată: {$this->card->stamps_balance} din {$program->stamps_required}."
            : "Card complet! Recompensă câștigată: {$program->reward_title}.";
    }
}
