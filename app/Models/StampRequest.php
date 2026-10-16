<?php

namespace App\Models;

use App\Enums\StampRequestStatus;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\StampRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $card_id
 * @property int $location_id
 * @property StampRequestStatus $status
 * @property int $quantity
 * @property int|null $handled_by
 * @property Carbon|null $handled_at
 * @property int|null $card_transaction_id
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StampRequest extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<StampRequestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'card_id',
        'location_id',
        'status',
        'quantity',
        'handled_by',
        'handled_at',
        'card_transaction_id',
        'expires_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'quantity' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StampRequestStatus::class,
            'quantity' => 'integer',
            'handled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, class-string<Model>>
     */
    protected function businessParents(): array
    {
        return [
            'card_id' => Card::class,
            'location_id' => Location::class,
            'card_transaction_id' => CardTransaction::class,
        ];
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Ștampila dată la aprobare.
     *
     * @return BelongsTo<CardTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(CardTransaction::class, 'card_transaction_id');
    }

    /**
     * Cererile care așteaptă un angajat: pending și neexpirate (expirarea e „leneșă”, ADR 012).
     *
     * @param  Builder<StampRequest>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', StampRequestStatus::Pending)->where('expires_at', '>', now());
    }

    /**
     * Statusul real: o cerere pending trecută de termen e expirată, chiar dacă rândul n-a fost încă marcat.
     */
    public function effectiveStatus(): StampRequestStatus
    {
        return $this->status === StampRequestStatus::Pending && ! $this->expires_at->isFuture()
            ? StampRequestStatus::Expired
            : $this->status;
    }

    public function isOpen(): bool
    {
        return $this->effectiveStatus() === StampRequestStatus::Pending;
    }

    /**
     * Momentul în care cererea s-a închis (aprobată, respinsă sau expirată); null cât e deschisă.
     */
    public function closedAt(): ?Carbon
    {
        return match (true) {
            $this->isOpen() => null,
            $this->handled_at !== null => $this->handled_at,
            $this->effectiveStatus() === StampRequestStatus::Expired => $this->expires_at,
            default => $this->updated_at,
        };
    }
}
