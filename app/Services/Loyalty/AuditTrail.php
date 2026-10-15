<?php

namespace App\Services\Loyalty;

use App\Models\AuditLog;
use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Scrie în audit_logs cine, ce și pe ce (SDD §6); „unde” stă în payload (location_id).
 */
class AuditTrail
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(User $actor, Card $card, string $action, Model $subject, array $payload = []): AuditLog
    {
        return AuditLog::query()->create([
            'business_id' => $card->business_id,
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'payload' => ['card_id' => $card->id, ...$payload],
        ]);
    }
}
