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

        $refusal = $this->refusal($original, $membership, $business);
        if ($refusal !== null) {
            throw new VoidNotAllowed($refusal);
        }
    }

    /**
     * Motivul pentru care tranzacția nu se poate anula, fără excepție (lista din dashboard ascunde butonul).
     * Regulile care cer lock pe card (ultima ștampilă activă, recompensă deja folosită) stau în revertStamp().
     */
    public function refusal(CardTransaction $original, Membership $membership, Business $business): ?string
    {
        return match (true) {
            ! $business->isActive() => 'Afacerea este suspendată.',
            ! in_array($original->type, [TransactionType::Stamp, TransactionType::Redeem], true) => 'Doar ștampilele și recompensele folosite pot fi anulate.',
            $original->voided_by_id !== null => 'Tranzacția a fost deja anulată.',
            $original->created_at->lt(now()->subHours(self::WINDOW_HOURS)) => 'Tranzacțiile pot fi anulate doar în primele 24 de ore.',
            $membership->role === Role::Manager && $membership->location_id !== null
                && $original->location_id !== $membership->location_id => 'Poți anula doar tranzacțiile din locația ta.',
            default => null,
        };
    }

    /**
     * Regulile de ordine pentru ștampile (ADR 004), în bloc: o ștampilă se anulează doar dacă e ultima mișcare
     * activă de sold a cardului (stamp/expire/adjust) și dacă recompensa emisă de ea nu a fost folosită.
     * Folosită și de lista din dashboard (TransactionLog), ca butonul să urmeze exact aceleași reguli.
     *
     * @param  iterable<CardTransaction>  $stamps
     * @return array<int, string> id-ul ștampilei => motivul refuzului
     */
    public function blockedStamps(iterable $stamps): array
    {
        $stamps = collect($stamps)->filter(fn (CardTransaction $transaction) => $transaction->type === TransactionType::Stamp);
        if ($stamps->isEmpty()) {
            return [];
        }

        $latestByCard = CardTransaction::query()
            ->whereIn('card_id', $stamps->pluck('card_id')->unique())
            ->whereIn('type', [TransactionType::Stamp, TransactionType::Expire, TransactionType::Adjust])
            ->whereNull('voided_by_id')
            ->groupBy('card_id')
            ->selectRaw('card_id, max(id) as latest_id')
            ->pluck('latest_id', 'card_id');

        $redeemedIssuers = Reward::query()
            ->whereIn('issued_by_transaction_id', $stamps->pluck('id'))
            ->where('status', RewardStatus::Redeemed)
            ->pluck('issued_by_transaction_id')
            ->flip();

        $blocked = [];
        foreach ($stamps as $stamp) {
            if ((int) ($latestByCard[$stamp->card_id] ?? 0) > $stamp->id) {
                $blocked[$stamp->id] = 'Doar ultima ștampilă a cardului poate fi anulată.';
            } elseif ($redeemedIssuers->has($stamp->id)) {
                $blocked[$stamp->id] = 'Recompensa emisă de această ștampilă a fost deja folosită.';
            }
        }

        return $blocked;
    }

    /**
     * Soldul revine la cel dinaintea ștampilei; recompensele emise de ea se revocă.
     */
    private function revertStamp(Card $card, CardTransaction $stamp): void
    {
        $blocked = $this->blockedStamps([$stamp]);
        if (isset($blocked[$stamp->id])) {
            throw new VoidNotAllowed($blocked[$stamp->id]);
        }

        $rewards = Reward::query()->where('issued_by_transaction_id', $stamp->id)->get();

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
