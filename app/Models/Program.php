<?php

namespace App\Models;

use App\Enums\ProgramStatus;
use App\Enums\ProgramType;
use App\Enums\StampIcon;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property ProgramType $type
 * @property int $stamps_required
 * @property string $reward_title
 * @property string|null $reward_description
 * @property int $max_stamps_per_visit
 * @property int $cooldown_minutes
 * @property int|null $daily_limit
 * @property int|null $stamps_expire_days
 * @property int|null $reward_expire_days
 * @property bool $rollover_extra
 * @property string|null $color
 * @property StampIcon $stamp_icon
 * @property ProgramStatus $status
 * @property string|null $terms
 * @property-read Business $business
 */
class Program extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ProgramFactory> */
    use HasFactory;

    /**
     * Culoarea implicită a cardului: verdele din logo LoyUp (ADR 017).
     */
    public const DEFAULT_COLOR = '#01A760';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'name',
        'type',
        'stamps_required',
        'reward_title',
        'reward_description',
        'max_stamps_per_visit',
        'cooldown_minutes',
        'daily_limit',
        'stamps_expire_days',
        'reward_expire_days',
        'rollover_extra',
        'color',
        'stamp_icon',
        'status',
        'terms',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'stamps',
        'status' => 'draft',
        'max_stamps_per_visit' => 1,
        'cooldown_minutes' => 30,
        'daily_limit' => 1,
        'rollover_extra' => false,
        'stamp_icon' => 'star',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProgramType::class,
            'status' => ProgramStatus::class,
            'stamps_required' => 'integer',
            'max_stamps_per_visit' => 'integer',
            'cooldown_minutes' => 'integer',
            'daily_limit' => 'integer',
            'stamps_expire_days' => 'integer',
            'reward_expire_days' => 'integer',
            'rollover_extra' => 'boolean',
            'stamp_icon' => StampIcon::class,
        ];
    }

    public function isActive(): bool
    {
        return $this->status === ProgramStatus::Active;
    }

    /**
     * Câte butoane „+N ștampile” vede angajatul (ecranul cardului și panoul de cereri): până la max/vizită, cel mult 5.
     */
    public function quickStampCount(): int
    {
        return max(1, min($this->max_stamps_per_visit, 5));
    }

    /**
     * Culoarea cardului: a programului, altfel a brandului, altfel verdele LoyUp.
     */
    public function displayColor(): string
    {
        return $this->color ?? self::fallbackColorFor($this->business);
    }

    /**
     * Culoarea unui program fără culoare proprie: a brandului, altfel verdele LoyUp.
     */
    public static function fallbackColorFor(Business $business): string
    {
        return $business->brand_color ?? self::DEFAULT_COLOR;
    }

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
