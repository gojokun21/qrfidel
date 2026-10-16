<?php

namespace App\Services\Loyalty;

use App\Enums\BusinessStatus;
use App\Enums\RewardStatus;
use App\Enums\StampRequestStatus;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Program;
use App\Models\Reward;
use App\Models\StampRequest;
use App\Services\Loyalty\Concerns\GuardsCardActions;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Expirările rulate de scheduler (SDD §8, ADR 013): ștampilele după inactivitate, recompensele trecute de
 * `expires_at` și cererile de la tejghea rămase pending. Fiecare card se tratează în tranzacția lui, cu lock,
 * iar condiția se verifică din nou sub lock (o ștampilă dată între timp oprește expirarea).
 */
class ExpirationService
{
    use GuardsCardActions;

    private const CHUNK = 200;

    public function __construct(
        private readonly AuditTrail $audit,
        private readonly CurrentBusiness $currentBusiness,
    ) {}

    /**
     * Cardurile fără ștampilă de `stamps_expire_days` zile își pierd soldul printr-o tranzacție `expire`.
     * Afacerile suspendate nu se ating (clientul nu putea primi ștampile între timp).
     *
     * @return int numărul de carduri expirate
     */
    public function expireStamps(): int
    {
        return $this->currentBusiness->runAs(null, function () {
            $expired = 0;

            Card::query()
                ->select('cards.*')
                ->join('programs', 'programs.id', '=', 'cards.program_id')
                ->join('businesses', 'businesses.id', '=', 'cards.business_id')
                ->where('cards.stamps_balance', '>', 0)
                ->whereNotNull('cards.last_stamp_at')
                ->whereNotNull('programs.stamps_expire_days')
                ->where('businesses.status', BusinessStatus::Active)
                ->whereRaw('cards.last_stamp_at <= ?::timestamp - make_interval(days => programs.stamps_expire_days)', [now()->format('Y-m-d H:i:s')])
                ->chunkById(self::CHUNK, function (Collection $cards) use (&$expired) {
                    foreach ($cards as $card) {
                        $expired += $this->expireCardStamps($card->id) ? 1 : 0;
                    }
                }, 'cards.id', 'id');

            return $expired;
        });
    }

    /**
     * Recompensele disponibile trecute de `expires_at` devin `expired`. Fără tranzacție în jurnal: `rewards`
     * e propriul registru, iar o tranzacție `expire` ar bloca anularea ultimei ștampile (ADR 004, 013).
     *
     * @return int numărul de recompense expirate
     */
    public function expireRewards(): int
    {
        return $this->currentBusiness->runAs(null, function () {
            $expired = 0;

            // Cardurile cu recompense expirate, pe bucăți (memorie și interogări mărginite și la un backlog mare).
            Card::query()
                ->select('cards.id')
                ->whereExists(fn ($query) => $query->selectRaw('1')
                    ->from('rewards')
                    ->whereColumn('rewards.card_id', 'cards.id')
                    ->where('rewards.status', RewardStatus::Available)
                    ->whereNotNull('rewards.expires_at')
                    ->where('rewards.expires_at', '<=', now()))
                ->chunkById(self::CHUNK, function (Collection $cards) use (&$expired) {
                    foreach ($cards as $card) {
                        $expired += $this->expireCardRewards($card->id);
                    }
                });

            return $expired;
        });
    }

    /**
     * Cererile pending trecute de termen devin `expired` (până acum expirarea era doar „leneșă”, ADR 012).
     * Un UPDATE în bloc: Postgres reverifică condiția după lock, deci o cerere aprobată între timp nu se atinge.
     *
     * @return int numărul de cereri expirate
     */
    public function expireStampRequests(): int
    {
        return $this->currentBusiness->runAs(null, fn () => StampRequest::query()
            ->where('status', StampRequestStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update(['status' => StampRequestStatus::Expired]));
    }

    private function expireCardStamps(int $cardId): bool
    {
        return $this->transactionally(function () use ($cardId) {
            $card = $this->lockCard($cardId);
            /** @var Program $program */
            $program = $card->program;
            /** @var Business $business */
            $business = $card->business;

            $due = $card->stamps_balance > 0
                && $card->last_stamp_at !== null
                && $program->stamps_expire_days !== null
                && $business->isActive()
                && $card->last_stamp_at->lte(now()->subDays($program->stamps_expire_days));

            if (! $due) {
                return false;
            }

            $balance = $card->stamps_balance;
            /** @var Carbon $lastStampAt */
            $lastStampAt = $card->last_stamp_at;

            // Cheia leagă expirarea de ultima ștampilă: același card nu expiră de două ori pentru aceeași inactivitate.
            $key = "expire:{$card->id}:{$lastStampAt->getTimestamp()}";
            if (CardTransaction::query()->where('business_id', $card->business_id)->where('idempotency_key', $key)->exists()) {
                return false;
            }

            $transaction = CardTransaction::query()->create([
                'business_id' => $card->business_id,
                'card_id' => $card->id,
                'type' => TransactionType::Expire,
                'quantity' => -$balance,
                'idempotency_key' => $key,
                'meta' => [
                    'balance_before' => $balance,
                    'balance_after' => 0,
                    'reason' => 'inactivity',
                    'last_stamp_at' => $lastStampAt->toIso8601String(),
                ],
            ]);

            $card->stamps_balance = 0;
            $card->save();

            $this->audit->record(null, $card, 'card.stamps_expired', $transaction, [
                'quantity' => $balance,
                'expire_days' => $program->stamps_expire_days,
                'last_stamp_at' => $lastStampAt->toIso8601String(),
            ]);

            return true;
        });
    }

    private function expireCardRewards(int $cardId): int
    {
        return $this->transactionally(function () use ($cardId) {
            $card = $this->lockCard($cardId);

            $rewardIds = Reward::query()
                ->where('card_id', $card->id)
                ->where('status', RewardStatus::Available)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->orderBy('id')
                ->pluck('id');

            if ($rewardIds->isEmpty()) {
                return 0;
            }

            Reward::query()->whereKey($rewardIds)->update(['status' => RewardStatus::Expired]);

            // Cache-ul se reface din registrul recompenselor, nu doar se scade: așa se repară și un drift vechi.
            $card->rewards_available = Reward::query()->where('card_id', $card->id)->where('status', RewardStatus::Available)->count();
            $card->save();

            $this->audit->record(null, $card, 'reward.expired', $card, [
                'count' => $rewardIds->count(),
                'reward_ids' => $rewardIds->all(),
            ]);

            return $rewardIds->count();
        });
    }
}
