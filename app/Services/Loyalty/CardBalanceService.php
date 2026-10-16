<?php

namespace App\Services\Loyalty;

use App\Enums\RewardStatus;
use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Program;
use App\Models\Reward;
use App\Services\Loyalty\Concerns\GuardsCardActions;
use App\Support\Tenancy\CurrentBusiness;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * `cards:recalculate` (SDD §4): soldurile de pe `cards` sunt cache, sursa de adevăr e jurnalul.
 * - stamps_balance: replay pe tranzacțiile active stamp/expire/adjust; unde există `meta.balance_after`
 *   (scris de StampService și de expirare) se folosește direct, altfel se aplică regulile programului (seed);
 * - rewards_available: recompensele cu status `available` (registrul `rewards`);
 * - last_stamp_at: ultima ștampilă neanulată.
 * Nu scrie nimic în jurnal; o diferență reparată intră în audit (`card.recalculated`, actor sistem).
 */
class CardBalanceService
{
    use GuardsCardActions;

    public function __construct(
        private readonly AuditTrail $audit,
        private readonly CurrentBusiness $currentBusiness,
    ) {}

    /**
     * @param  Closure(Card, array<string, mixed>, array<string, mixed>): void|null  $onDrift  apelat pentru fiecare card cu diferențe
     * @return array{checked: int, drifted: int}
     */
    public function recalculateAll(?int $businessId = null, ?int $cardId = null, bool $dryRun = false, ?Closure $onDrift = null): array
    {
        return $this->currentBusiness->runAs(null, function () use ($businessId, $cardId, $dryRun, $onDrift) {
            $checked = 0;
            $drifted = 0;

            Card::query()
                ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
                ->when($cardId !== null, fn ($query) => $query->whereKey($cardId))
                ->chunkById(200, function (Collection $cards) use (&$checked, &$drifted, $dryRun, $onDrift) {
                    foreach ($cards as $card) {
                        $checked++;
                        $diff = $this->recalculate($card, $dryRun);

                        if ($diff !== null) {
                            $drifted++;
                            if ($onDrift !== null) {
                                $onDrift($card, $diff['before'], $diff['after']);
                            }
                        }
                    }
                });

            return ['checked' => $checked, 'drifted' => $drifted];
        });
    }

    /**
     * @return array{before: array<string, mixed>, after: array<string, mixed>}|null null când cache-ul e corect
     */
    public function recalculate(Card $card, bool $dryRun = false): ?array
    {
        $compute = function (Card $card) use ($dryRun): ?array {
            $before = $this->snapshot($card->stamps_balance, $card->rewards_available, $card->last_stamp_at);
            $after = $this->snapshot($this->stampsBalance($card), $this->rewardsAvailable($card), $this->lastStampAt($card));

            if ($before === $after) {
                return null;
            }

            if (! $dryRun) {
                $card->stamps_balance = $after['stamps_balance'];
                $card->rewards_available = $after['rewards_available'];
                $card->last_stamp_at = $after['last_stamp_at'] === null ? null : Carbon::parse($after['last_stamp_at']);
                $card->save();

                $this->audit->record(null, $card, 'card.recalculated', $card, ['before' => $before, 'after' => $after]);
            }

            return ['before' => $before, 'after' => $after];
        };

        return $dryRun
            ? $compute($card)
            : $this->transactionally(fn () => $compute($this->lockCard($card->id)));
    }

    /**
     * @return array{stamps_balance: int, rewards_available: int, last_stamp_at: string|null}
     */
    private function snapshot(int $stamps, int $rewards, ?Carbon $lastStampAt): array
    {
        return [
            'stamps_balance' => $stamps,
            'rewards_available' => $rewards,
            'last_stamp_at' => $lastStampAt?->copy()->utc()->toIso8601String(),
        ];
    }

    private function stampsBalance(Card $card): int
    {
        /** @var Program $program */
        $program = $card->program;

        $transactions = CardTransaction::query()
            ->where('card_id', $card->id)
            ->whereIn('type', [TransactionType::Stamp, TransactionType::Expire, TransactionType::Adjust])
            ->whereNull('voided_by_id')
            ->orderBy('id')
            ->get(['id', 'type', 'quantity', 'meta']);

        $balance = 0;

        foreach ($transactions as $transaction) {
            $recorded = $transaction->meta['balance_after'] ?? null;

            $balance = match (true) {
                is_int($recorded) => $recorded,
                $transaction->type === TransactionType::Stamp => RewardRules::applyStamps($program, $balance + $transaction->quantity)[0],
                default => max(0, $balance + $transaction->quantity),
            };
        }

        return $balance;
    }

    private function rewardsAvailable(Card $card): int
    {
        return Reward::query()->where('card_id', $card->id)->where('status', RewardStatus::Available)->count();
    }

    private function lastStampAt(Card $card): ?Carbon
    {
        $latest = CardTransaction::query()
            ->where('card_id', $card->id)
            ->where('type', TransactionType::Stamp)
            ->whereNull('voided_by_id')
            ->latest('id')
            ->value('created_at');

        return $latest === null ? null : Carbon::parse($latest);
    }
}
