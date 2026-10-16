<?php

namespace App\Services\Loyalty;

use App\Exceptions\InvalidScanTicket;
use App\Models\Card;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Tichetul de scanare (ADR 011): `QrTokenService::resolve()` consumă QR-ul, iar tichetul emis atunci dovedește
 * că ștampila sau recompensa vin după o scanare reală. E legat de card, angajat și locație, expiră repede și
 * permite o singură ștampilare și o singură folosire de recompensă.
 */
class ScanTicketService
{
    public const STAMP = 'stamp';

    public const REDEEM = 'redeem';

    private const ACTIONS = [self::STAMP, self::REDEEM];

    private const PATTERN = '/^[A-Za-z0-9_-]{43}$/';

    public function issue(Card $card, User $actor, Location $location): string
    {
        $ticket = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $ttl = $this->ttl();

        $this->store()->put($this->key($ticket), [
            'card_id' => $card->id,
            'user_id' => $actor->id,
            'location_id' => $location->id,
            'expires_at' => now()->getTimestamp() + $ttl,
        ], $ttl);

        return $ticket;
    }

    /**
     * Tichetul valabil al acestui angajat pentru acest card. Același răspuns pentru orice refuz:
     * nu spune dacă tichetul există pentru alt card sau alt angajat.
     *
     * @throws InvalidScanTicket
     */
    public function find(string $ticket, Card $card, User $actor): ScanTicket
    {
        $data = preg_match(self::PATTERN, $ticket) === 1 ? $this->store()->get($this->key($ticket)) : null;

        if (! is_array($data)
            || $data['card_id'] !== $card->id
            || $data['user_id'] !== $actor->id
            || $data['expires_at'] <= now()->getTimestamp()) {
            throw InvalidScanTicket::invalid();
        }

        // Fără scope: contextul curent poate lipsi (servicii apelate din afara unui request); afacerea vine din card.
        $location = Location::query()->withoutGlobalScopes()
            ->where('business_id', $card->business_id)
            ->find($data['location_id']) ?? throw InvalidScanTicket::invalid();

        return new ScanTicket(
            id: $ticket,
            location: $location,
            expiresAt: CarbonImmutable::createFromTimestamp($data['expires_at']),
            stampUsed: $this->store()->has($this->actionKey($ticket, self::STAMP)),
            redeemUsed: $this->store()->has($this->actionKey($ticket, self::REDEEM)),
        );
    }

    /**
     * Ca find(), dar întoarce null în loc să refuze (ecranul cardului arată atunci doar „Scanează din nou”).
     */
    public function check(?string $ticket, Card $card, User $actor): ?ScanTicket
    {
        try {
            return $ticket === null ? null : $this->find($ticket, $card, $actor);
        } catch (InvalidScanTicket) {
            return null;
        }
    }

    /**
     * Rezervă acțiunea pe tichet, atomic (SET NX). Aceeași cheie de idempotency trece din nou (dublu-click:
     * serviciul face replay), dar nu e o rezervare nouă: întoarce true doar când a rezervat-o acum.
     *
     * @throws InvalidScanTicket
     */
    public function claim(ScanTicket $ticket, string $action, string $idempotencyKey): bool
    {
        $key = $this->actionKey($ticket->id, $action);
        $ttl = max(1, $ticket->expiresAt->getTimestamp() - now()->getTimestamp());

        if ($this->store()->add($key, $idempotencyKey, $ttl)) {
            return true;
        }

        if ($this->store()->get($key) === $idempotencyKey) {
            return false;
        }

        throw InvalidScanTicket::alreadyUsed();
    }

    /**
     * Eliberează acțiunea refuzată de o regulă (cooldown, limită), ca angajatul să poată reîncerca pe aceeași scanare.
     * Doar cheia care a rezervat-o o poate elibera.
     */
    public function release(ScanTicket $ticket, string $action, string $idempotencyKey): void
    {
        $key = $this->actionKey($ticket->id, $action);

        if ($this->store()->get($key) === $idempotencyKey) {
            $this->store()->forget($key);
        }
    }

    private function actionKey(string $ticket, string $action): string
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException("Unknown scan action [{$action}].");
        }

        return $this->key($ticket).":{$action}";
    }

    private function key(string $ticket): string
    {
        return "scan:ticket:{$ticket}";
    }

    private function ttl(): int
    {
        return (int) config('loyalty.scan.ticket_ttl_seconds');
    }

    private function store(): Repository
    {
        return Cache::store(config('loyalty.scan.store'));
    }
}
