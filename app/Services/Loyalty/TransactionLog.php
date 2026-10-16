<?php

namespace App\Services\Loyalty;

use App\Enums\Role;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\CardTransaction;
use App\Models\Membership;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Tranzacțiile afacerii pentru manager/owner (pagina „Tranzacții”, ADR 013) și ce se poate anula din ele.
 * Managerul legat de o locație vede doar locația lui, ca în VoidService.
 */
class TransactionLog
{
    public const PER_PAGE = 25;

    public function __construct(private readonly VoidService $voids) {}

    /**
     * Filtrul de locație e ignorat pentru managerul legat de o locație: el vede mereu locația lui.
     *
     * @return LengthAwarePaginator<int, CardTransaction>
     */
    public function paginate(Membership $membership, ?int $locationId = null, ?TransactionType $type = null): LengthAwarePaginator
    {
        $locationId = $this->ownLocation($membership) ?? $locationId;

        return CardTransaction::query()
            ->where('business_id', $membership->business_id)
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->with(['card.customer', 'card.program', 'staff', 'location'])
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Locația la care e limitat membrul (managerul legat de o locație), altfel null.
     */
    public function ownLocation(Membership $membership): ?int
    {
        return $membership->role === Role::Manager ? $membership->location_id : null;
    }

    /**
     * Id-urile tranzacțiilor pe care membrul le poate anula acum, după aceleași reguli ca VoidService
     * (`refusal()` și `blockedStamps()`), calculate în bloc.
     *
     * @param  iterable<CardTransaction>  $transactions
     * @return array<int, true>
     */
    public function voidableIds(Membership $membership, Business $business, iterable $transactions): array
    {
        $candidates = collect($transactions)
            ->filter(fn (CardTransaction $transaction) => $this->voids->refusal($transaction, $membership, $business) === null);

        $blocked = $this->voids->blockedStamps($candidates);

        $voidable = [];
        foreach ($candidates as $transaction) {
            if (! isset($blocked[$transaction->id])) {
                $voidable[$transaction->id] = true;
            }
        }

        return $voidable;
    }
}
