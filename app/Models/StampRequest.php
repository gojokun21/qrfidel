<?php

namespace App\Models;

use App\Enums\StampRequestStatus;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\StampRequestFactory;
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
 * @property Carbon $expires_at
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
}
