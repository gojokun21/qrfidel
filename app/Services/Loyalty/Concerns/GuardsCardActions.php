<?php

namespace App\Services\Loyalty\Concerns;

use App\Enums\Role;
use App\Enums\TransactionType;
use App\Exceptions\IdempotencyConflict;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Location;
use App\Models\Membership;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Pașii comuni ai acțiunilor de staff pe un card: tranzacție DB cu lock pe card, autorizare și idempotency.
 */
trait GuardsCardActions
{
    private const IDEMPOTENCY_INDEX = 'card_transactions_business_id_idempotency_key_unique';

    /**
     * Rulează acțiunea într-o tranzacție DB. Dacă o cerere paralelă pe alt card a inserat aceeași cheie
     * între timp, indexul unic o respinge; a doua încercare găsește cheia și o tratează ca replay sau conflict.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $action
     * @return TResult
     */
    protected function transactionally(Closure $action): mixed
    {
        try {
            return DB::transaction($action);
        } catch (UniqueConstraintViolationException $e) {
            if (! str_contains($e->getMessage(), self::IDEMPOTENCY_INDEX)) {
                throw $e;
            }

            return DB::transaction($action);
        }
    }

    /**
     * SELECT … FOR UPDATE pe card: acțiunile pe același card se serializează.
     */
    protected function lockCard(int $cardId): Card
    {
        return Card::query()->whereKey($cardId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Actorul trebuie să fie membru al afacerii cardului cu cel puțin rolul dat (verificat și de Policies).
     */
    protected function authorizeMember(User $actor, Card $card, Role $minimum, ?Location $location = null): Membership
    {
        $membership = $actor->membershipIn($card->business_id);

        if ($membership === null || ! $membership->role->atLeast($minimum)) {
            throw new AuthorizationException('Nu ai dreptul să faci această acțiune pe acest card.');
        }

        // Staff-ul și managerii legați de o locație acționează doar acolo (altfel restricția de void pe locație s-ar ocoli).
        if ($location !== null && $membership->role !== Role::Owner
            && $membership->location_id !== null && $membership->location_id !== $location->id) {
            throw new AuthorizationException('Poți lucra doar în locația ta.');
        }

        return $membership;
    }

    /**
     * Tranzacția deja creată cu această cheie (replay) sau null. Cheia folosită pe alt card, pentru
     * altă acțiune sau cu alți parametri e un conflict, nu un replay.
     *
     * @param  array<string, int|null>  $sameAttributes  atribute care trebuie să coincidă cu cererea originală
     */
    protected function findReplay(Card $card, string $idempotencyKey, TransactionType $type, array $sameAttributes = []): ?CardTransaction
    {
        $existing = CardTransaction::query()
            ->withoutGlobalScopes()
            ->where('business_id', $card->business_id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing === null) {
            return null;
        }

        $sameRequest = $existing->card_id === $card->id
            && $existing->type === $type
            && collect($sameAttributes)->every(fn (?int $value, string $attribute) => $existing->getAttribute($attribute) === $value);

        if (! $sameRequest) {
            throw new IdempotencyConflict('Cheia de idempotency a fost deja folosită pentru o altă acțiune.');
        }

        return $existing;
    }
}
