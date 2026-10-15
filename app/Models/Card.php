<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cardul unui client într-un program. Soldurile sunt cache; sursa de adevăr e card_transactions.
 *
 * @property int $id
 * @property int $business_id
 * @property int $program_id
 * @property int $customer_id
 * @property string $public_id
 * @property string $secret
 * @property int $stamps_balance
 * @property int $rewards_available
 * @property Carbon|null $last_stamp_at
 */
class Card extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<CardFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * Soldurile și secretul nu sunt mass-assignable: le scriu doar serviciile din app/Services/Loyalty.
     *
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'program_id',
        'customer_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'secret',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stamps_balance' => 0,
        'rewards_available' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'stamps_balance' => 'integer',
            'rewards_available' => 'integer',
            'last_stamp_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Card $card): void {
            $card->secret ??= self::newSecret();
        });
    }

    /**
     * Secretul cu care QrTokenService semnează QR-ul cardului.
     */
    public static function newSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * public_id e ULID; cheia primară rămâne numerică.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return array<string, class-string<Model>>
     */
    protected function businessParents(): array
    {
        return ['program_id' => Program::class];
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<CardTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CardTransaction::class);
    }

    /**
     * @return HasMany<Reward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    /**
     * @return HasMany<StampRequest, $this>
     */
    public function stampRequests(): HasMany
    {
        return $this->hasMany(StampRequest::class);
    }
}
