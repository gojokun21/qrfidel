<?php

namespace App\Services\Loyalty;

use App\Enums\Role;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Exceptions\StampRuleViolation;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Location;
use App\Models\Program;
use App\Models\Reward;
use App\Models\User;
use App\Services\Loyalty\Concerns\GuardsCardActions;

/**
 * Adăugarea de ștampile (SDD §5.4): o singură tranzacție DB, cu lock pe card.
 */
class StampService
{
    use GuardsCardActions;

    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @param  array<string, mixed>  $meta  context suplimentar salvat pe tranzacție (de ex. id-ul cererii)
     *
     * @throws StampRuleViolation
     */
    public function add(
        Card $card,
        User $staff,
        Location $location,
        int $quantity,
        string $idempotencyKey,
        TransactionSource $source = TransactionSource::Scan,
        array $meta = [],
    ): StampResult {
        return $this->transactionally(function () use ($card, $staff, $location, $quantity, $idempotencyKey, $source, $meta) {
            $card = $this->lockCard($card->id);
            $this->authorizeMember($staff, $card, Role::Staff, $location);

            $replay = $this->findReplay($card, $idempotencyKey, TransactionType::Stamp, [
                'quantity' => $quantity,
                'location_id' => $location->id,
            ]);
            if ($replay !== null) {
                return new StampResult($card, $replay, $this->rewardsIssuedBy($replay), replayed: true);
            }

            /** @var Program $program */
            $program = $card->program;
            /** @var Business $business */
            $business = $card->business;

            $this->assertRules($card, $program, $business, $quantity);

            $balanceBefore = $card->stamps_balance;
            [$balanceAfter, $rewardCount] = RewardRules::applyStamps($program, $balanceBefore + $quantity);

            $transaction = CardTransaction::query()->create([
                'business_id' => $card->business_id,
                'card_id' => $card->id,
                'type' => TransactionType::Stamp,
                'quantity' => $quantity,
                'staff_user_id' => $staff->id,
                'location_id' => $location->id,
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'meta' => [...$meta, 'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter],
            ]);

            $rewards = [];
            for ($i = 0; $i < $rewardCount; $i++) {
                $rewards[] = Reward::query()->create([
                    'business_id' => $card->business_id,
                    'card_id' => $card->id,
                    'issued_by_transaction_id' => $transaction->id,
                    'issued_at' => now(),
                    'expires_at' => $program->reward_expire_days === null ? null : now()->addDays($program->reward_expire_days),
                ]);
            }

            $card->stamps_balance = $balanceAfter;
            $card->rewards_available += $rewardCount;
            $card->last_stamp_at = $transaction->created_at;
            $card->save();

            $this->audit->record($staff, $card, 'card.stamped', $transaction, [
                'quantity' => $quantity,
                'location_id' => $location->id,
                'source' => $source->value,
                'rewards_issued' => $rewardCount,
            ]);

            return new StampResult($card, $transaction, $rewards, replayed: false);
        });
    }

    private function assertRules(Card $card, Program $program, Business $business, int $quantity): void
    {
        if (! $business->isActive()) {
            throw new StampRuleViolation('Afacerea este suspendată.');
        }

        if (! $program->isActive()) {
            throw new StampRuleViolation('Programul nu este activ.');
        }

        if ($quantity < 1) {
            throw new StampRuleViolation('Numărul de ștampile trebuie să fie cel puțin 1.');
        }

        if ($quantity > $program->max_stamps_per_visit) {
            throw new StampRuleViolation("Maxim {$program->max_stamps_per_visit} ștampile pe vizită.");
        }

        $this->assertCooldown($card, $program);
        $this->assertDailyLimit($card, $program, $business, $quantity);
    }

    private function assertCooldown(Card $card, Program $program): void
    {
        if ($program->cooldown_minutes === 0 || $card->last_stamp_at === null) {
            return;
        }

        $elapsed = max(0, (int) $card->last_stamp_at->diffInSeconds(now()));
        $remaining = $program->cooldown_minutes * 60 - $elapsed;

        if ($remaining > 0) {
            $ago = intdiv($elapsed, 60);
            $wait = (int) ceil($remaining / 60);

            throw new StampRuleViolation("Ultima ștampilă acum {$ago} min – așteaptă {$wait} min.");
        }
    }

    /**
     * Ștampilele (nu vizitele) de azi, în timezone-ul afacerii; cele anulate nu se numără.
     */
    private function assertDailyLimit(Card $card, Program $program, Business $business, int $quantity): void
    {
        if ($program->daily_limit === null) {
            return;
        }

        $startOfDay = now()->setTimezone($business->timezone)->startOfDay()->utc();

        $today = (int) CardTransaction::query()
            ->where('card_id', $card->id)
            ->where('type', TransactionType::Stamp)
            ->whereNull('voided_by_id')
            ->where('created_at', '>=', $startOfDay)
            ->sum('quantity');

        if ($today + $quantity > $program->daily_limit) {
            throw new StampRuleViolation("Limita zilnică de {$program->daily_limit} ștampile a fost atinsă (azi: {$today}).");
        }
    }

    /**
     * @return list<Reward>
     */
    private function rewardsIssuedBy(CardTransaction $transaction): array
    {
        return array_values(Reward::query()
            ->where('issued_by_transaction_id', $transaction->id)
            ->orderBy('id')
            ->get()
            ->all());
    }
}
