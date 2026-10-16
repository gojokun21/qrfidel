<?php

namespace App\Services\Loyalty;

use App\Enums\RewardStatus;
use App\Enums\Role;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Exceptions\RewardUnavailable;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Location;
use App\Models\Reward;
use App\Models\User;
use App\Services\Loyalty\Concerns\GuardsCardActions;

/**
 * Folosirea recompenselor (SDD §5.5). Recompensele câștigate rămân valabile și pe un program pus pe pauză
 * sau arhivat (ADR 004); doar expirarea și o afacere suspendată le blochează.
 */
class RewardService
{
    use GuardsCardActions;

    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @throws RewardUnavailable
     */
    public function redeem(
        Card $card,
        User $staff,
        Location $location,
        string $idempotencyKey,
        TransactionSource $source = TransactionSource::Scan,
    ): RedeemResult {
        return $this->transactionally(function () use ($card, $staff, $location, $idempotencyKey, $source) {
            $card = $this->lockCard($card->id);
            $this->authorizeMember($staff, $card, Role::Staff, $location);

            $replay = $this->findReplay($card, $idempotencyKey, TransactionType::Redeem, ['location_id' => $location->id]);
            if ($replay !== null) {
                $reward = Reward::query()->findOrFail($replay->meta['reward_id'] ?? null);

                return new RedeemResult($card, $replay, $reward, replayed: true);
            }

            /** @var Business $business */
            $business = $card->business;
            if (! $business->isActive()) {
                throw new RewardUnavailable('Afacerea este suspendată.');
            }

            $reward = Reward::query()
                ->where('card_id', $card->id)
                ->usable()
                ->orderBy('issued_at')
                ->orderBy('id')
                ->first();

            if ($reward === null) {
                throw new RewardUnavailable('Nicio recompensă disponibilă pe acest card.');
            }

            $reward->status = RewardStatus::Redeemed;
            $reward->redeemed_at = now();
            $reward->redeemed_by = $staff->id;
            $reward->location_id = $location->id;
            $reward->save();

            $transaction = CardTransaction::query()->create([
                'business_id' => $card->business_id,
                'card_id' => $card->id,
                'type' => TransactionType::Redeem,
                'quantity' => 1,
                'staff_user_id' => $staff->id,
                'location_id' => $location->id,
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'meta' => ['reward_id' => $reward->id],
            ]);

            $card->rewards_available--;
            $card->save();

            $this->audit->record($staff, $card, 'reward.redeemed', $transaction, [
                'reward_id' => $reward->id,
                'location_id' => $location->id,
            ]);

            return new RedeemResult($card, $transaction, $reward, replayed: false);
        });
    }
}
