<?php

namespace App\Services\Loyalty;

use App\Exceptions\InvalidScanTicket;
use App\Exceptions\LoyaltyException;
use App\Models\Card;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Acțiunile angajatului după o scanare (SDD §5.2, ADR 011): fiecare cere tichetul emis la scanare, iar locația
 * vine din tichet, nu din request. Regulile rămân în StampService / RewardService.
 */
class StaffCardActions
{
    public function __construct(
        private readonly ScanTicketService $tickets,
        private readonly StampService $stamps,
        private readonly RewardService $rewards,
    ) {}

    /**
     * @throws InvalidScanTicket
     * @throws AuthorizationException
     */
    public function stamp(Card $card, User $staff, string $ticket, int $quantity, string $idempotencyKey): StampResult
    {
        return $this->onTicket($card, $staff, $ticket, ScanTicketService::STAMP, $idempotencyKey,
            fn (ScanTicket $scan) => $this->stamps->add($card, $staff, $scan->location, $quantity, $idempotencyKey));
    }

    /**
     * @throws InvalidScanTicket
     * @throws AuthorizationException
     */
    public function redeem(Card $card, User $staff, string $ticket, string $idempotencyKey): RedeemResult
    {
        return $this->onTicket($card, $staff, $ticket, ScanTicketService::REDEEM, $idempotencyKey,
            fn (ScanTicket $scan) => $this->rewards->redeem($card, $staff, $scan->location, $idempotencyKey));
    }

    /**
     * @template TResult
     *
     * @param  Closure(ScanTicket): TResult  $action
     * @return TResult
     */
    private function onTicket(Card $card, User $staff, string $ticket, string $name, string $idempotencyKey, Closure $action): mixed
    {
        $scan = $this->tickets->find($ticket, $card, $staff);
        $claimed = $this->tickets->claim($scan, $name, $idempotencyKey);

        try {
            return $action($scan);
        } catch (LoyaltyException|AuthorizationException $e) {
            // Doar un refuz de business eliberează slotul, și doar rezervarea făcută acum: o cheie repetată care
            // eșuează (de ex. conflict) nu eliberează slotul unei ștampile deja date. O eroare tranzitorie (deadlock,
            // conexiune) îl păstrează: o cerere paralelă cu aceeași cheie poate reuși între timp, iar o eliberare
            // ar permite încă o ștampilă pe aceeași scanare.
            if ($claimed) {
                $this->tickets->release($scan, $name, $idempotencyKey);
            }

            throw $e;
        }
    }
}
