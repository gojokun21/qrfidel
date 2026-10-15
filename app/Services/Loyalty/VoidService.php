<?php

namespace App\Services\Loyalty;

use App\Enums\RewardStatus;
use App\Enums\Role;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\VoidNotAllowed;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Membership;
use App\Models\Reward;
use App\Models\User;
use App\Services\Loyalty\Concerns\GuardsCardActions;
use Illuminate\Support\Carbon;

/**
 * Anularea tranzacțiilor (SDD §5.6): o tranzacție `void` inversă, nimic nu se șterge.
 * Reguli (ADR 004): doar `stamp` și `redeem`, în maxim 24h, manager pe locația lui sau owner,
 * iar o ștampilă doar dacă e ultima ștampilă activă a cardului și recompensa ei nu a fost folosită.
 */
class VoidService
{
    use GuardsCardActions;

    public const WINDOW_HOURS = 24;

    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @throws VoidNotAllowed
     */
    public function void(CardTransaction $transaction, User $actor, string $idempotencyKey, ?string $reason = null): VoidResult
    {
        return $this->transactionally(function () use ($transaction, $actor, $idempotencyKey, $reason) {
            $card = $this->lockCard($transaction->card_id);
            $membership = $this->authorizeMember($actor, $card, Role::Manager);
            $original = CardTransaction::query()->whereKey($transaction->id)->firstOrFail();

            $replay = $this->findReplay($card, $idempotencyKey, TransactionType::Void);
            if ($replay !== null) {
                if (($replay->meta['voided_transaction_id'] ?? null) !== $original->id) {
                    throw new IdempotencyConflict('Cheia de idempotency a fost deja folosită pentru o altă anulare.');
                }

                return new VoidResult($card, $replay, replayed: true);
            }

            $this->assertVoidable($card, $original, $membership);

            if ($original->type === TransactionType::Stamp) {
                $this->revertStamp($card, $original);
            } else {
                $this->revertRedeem($card, $original);
            }

            $void = CardTransaction::query()->create([
                'business_id' => $card->business_id,
                'card_id' => $card->id,
                'type' => TransactionType::Void,
                'quantity' => -$original->quantity,
                'staff_user_id' => $actor->id,
                'location_id' => $original->location_id,
                'source' => TransactionSource::Manual,
                'idempotency_key' => $idempotencyKey,
                'meta' => ['voided_transaction_id' => $original->id, 'reason' => $reason],
            ]);

            $original->voided_by_id = $void->id;
            $original->save();
            $card->save();

            $this->audit->record($actor, $card, 'transaction.voided', $void, [
                'voided_transaction_id' => $original->id,
                'voided_type' => $original->type->value,
                'location_id' => $original->location_id,
                'reason' => $reason,
            ]);

            return new VoidResult($card, $void, replayed: false);
        });
    }

    private function assertVoidable(Card $card, CardTransaction $original, Membership $membership): void
    {
        /** @var Business $business */
        $business = $card->business;
        if (! $business->isActive()) {
            throw new VoidNotAllowed('Afacerea este suspendată.');
        }

        if (! in_array($original->type, [TransactionType::Stamp, TransactionType::Redeem], true)) {
            throw new VoidNotAllowed('Doar ștampilele și recompensele folosite pot fi anulate.');
        }

        if ($original->voided_by_id !== null) {
            throw new VoidNotAllowed('Tranzacția a fost deja anulată.');
        }

        if ($original->created_at->lt(now()->subHours(self::WINDOW_HOURS))) {
            throw new VoidNotAllowed('Tranzacțiile pot fi anulate doar în primele 24 de ore.');
        }

        $restrictedToLocation = $membership->role === Role::Manager && $membership->location_id !== null;
        if ($restrictedToLocation && $original->location_id !== $membership->location_id) {
            throw new VoidNotAllowed('Poți anula doar tranzacțiile din locația ta.');
        }
    }

    /**
     * Soldul revine la cel dinaintea ștampilei; recompensele emise de ea se revocă.
     */
    private function revertStamp(Card $card, CardTransaction $stamp): void
    {
        $laterBalanceChange = CardTransaction::query()
            ->where('card_id', $card->id)
            ->where('id', '>', $stamp->id)
            ->whereIn('type', [TransactionType::Stamp, TransactionType::Expire, TransactionType::Adjust])
            ->whereNull('voided_by_id')
            ->exists();

        if ($laterBalanceChange) {
            throw new VoidNotAllowed('Doar ultima ștampilă a cardului poate fi anulată.');
        }

        $rewards = Reward::query()->where('issued_by_transaction_id', $stamp->id)->get();

        if ($rewards->contains(fn (Reward $reward) => $reward->status === RewardStatus::Redeemed)) {
            throw new VoidNotAllowed('Recompensa emisă de această ștampilă a fost deja folosită.');
        }

        $revokedAvailable = 0;
        foreach ($rewards as $reward) {
            if ($reward->status === RewardStatus::Available) {
                $revokedAvailable++;
            }
            $reward->status = RewardStatus::Voided;
            $reward->save();
        }

        // Tranzacțiile fără meta (de ex. din seed) nu pot emite recompense urmăribile; scădem doar cantitatea.
        $balanceBefore = $stamp->meta['balance_before'] ?? max(0, $card->stamps_balance - $stamp->quantity);

        $card->stamps_balance = (int) $balanceBefore;
        $card->rewards_available -= $revokedAvailable;
        $card->last_stamp_at = $this->lastActiveStampAt($card, except: $stamp);
    }

    private function revertRedeem(Card $card, CardTransaction $redeem): void
    {
        $reward = Reward::query()
            ->where('card_id', $card->id)
            ->whereKey($redeem->meta['reward_id'] ?? null)
            ->first();

        if ($reward === null) {
            throw new VoidNotAllowed('Recompensa folosită nu mai poate fi găsită.');
        }

        // O recompensă care a expirat între timp nu redevine folosibilă.
        $expired = $reward->expires_at !== null && $reward->expires_at->lte(now());

        $reward->status = $expired ? RewardStatus::Expired : RewardStatus::Available;
        $reward->redeemed_at = null;
        $reward->redeemed_by = null;
        $reward->location_id = null;
        $reward->save();

        if (! $expired) {
            $card->rewards_available++;
        }
    }

    private function lastActiveStampAt(Card $card, CardTransaction $except): ?Carbon
    {
        $latest = CardTransaction::query()
            ->where('card_id', $card->id)
            ->whereKeyNot($except->id)
            ->where('type', TransactionType::Stamp)
            ->whereNull('voided_by_id')
            ->latest('id')
            ->first();

        return $latest?->created_at;
    }
}
