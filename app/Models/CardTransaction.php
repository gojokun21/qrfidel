<?php

namespace App\Models;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\CardTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Jurnal append-only: tranzacțiile nu se modifică și nu se șterg, doar se anulează printr-o tranzacție inversă.
 * Singura modificare permisă e setarea o singură dată a voided_by_id (garantat și de un trigger Postgres).
 *
 * @property int $id
 * @property int $business_id
 * @property int $card_id
 * @property TransactionType $type
 * @property int $quantity
 * @property int|null $staff_user_id
 * @property int|null $location_id
 * @property TransactionSource|null $source
 * @property string|null $idempotency_key
 * @property int|null $voided_by_id
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 */
class CardTransaction extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<CardTransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'card_id',
        'type',
        'quantity',
        'staff_user_id',
        'location_id',
        'source',
        'idempotency_key',
        'voided_by_id',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'source' => TransactionSource::class,
            'quantity' => 'integer',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (CardTransaction $transaction): void {
            $onlyMarksVoided = array_keys($transaction->getDirty()) === ['voided_by_id']
                && $transaction->getOriginal('voided_by_id') === null;

            if (! $onlyMarksVoided) {
                throw new LogicException('Card transactions are append-only; create a void transaction instead.');
            }

            $isVoidOfSameCard = $transaction->voided_by_id !== $transaction->id
                && static::query()->withoutGlobalScopes()
                    ->whereKey($transaction->voided_by_id)
                    ->where('card_id', $transaction->card_id)
                    ->where('type', TransactionType::Void)
                    ->exists();

            if (! $isVoidOfSameCard) {
                throw new LogicException('A transaction can only be voided by a void transaction of the same card.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Card transactions are append-only and cannot be deleted.');
        });
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
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<CardTransaction, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(CardTransaction::class, 'voided_by_id');
    }
}
