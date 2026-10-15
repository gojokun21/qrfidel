<?php

namespace App\Models;

use App\Enums\RewardStatus;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\RewardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $card_id
 * @property RewardStatus $status
 * @property Carbon $issued_at
 * @property Carbon|null $redeemed_at
 * @property int|null $redeemed_by
 * @property int|null $location_id
 * @property Carbon|null $expires_at
 */
class Reward extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<RewardFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'card_id',
        'status',
        'issued_at',
        'redeemed_at',
        'redeemed_by',
        'location_id',
        'expires_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'available',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RewardStatus::class,
            'issued_at' => 'datetime',
            'redeemed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
