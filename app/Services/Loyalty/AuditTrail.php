<?php

namespace App\Services\Loyalty;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Card;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Scrie în audit_logs cine, ce și pe ce (SDD §6); „unde” stă în payload (location_id), iar IP-ul în ip_address.
 * Jurnalul e append-only (model + trigger, ADR 013).
 */
class AuditTrail
{
    /**
     * Actorul e angajatul (stamp, redeem, void), clientul (înscriere, cerere) sau null pentru sistem
     * (joburile de expirare, `cards:recalculate`).
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(User|Customer|null $actor, Card $card, string $action, Model $subject, array $payload = []): AuditLog
    {
        return AuditLog::query()->create([
            'business_id' => $card->business_id,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'payload' => ['card_id' => $card->id, ...$payload],
            'ip_address' => $this->ip(),
        ]);
    }

    /**
     * Acțiuni din dashboard (afacere, locații, echipă) care nu țin de un card.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordForBusiness(User $actor, Business|int $business, string $action, Model $subject, array $payload = []): AuditLog
    {
        return AuditLog::query()->create([
            'business_id' => $business instanceof Business ? $business->id : $business,
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'payload' => $payload === [] ? null : $payload,
            'ip_address' => $this->ip(),
        ]);
    }

    /**
     * IP-ul doar pentru acțiunile venite printr-o rută web; în console și în worker request-ul e unul fictiv.
     */
    private function ip(): ?string
    {
        $request = request();

        return $request->route() !== null ? $request->ip() : null;
    }
}
