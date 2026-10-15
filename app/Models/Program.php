<?php

namespace App\Models;

use App\Enums\ProgramStatus;
use App\Enums\ProgramType;
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
 * @property ProgramStatus $status
 * @property string|null $terms
 */
class Program extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ProgramFactory> */
    use HasFactory;

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
        ];
    }

    public function isActive(): bool
    {
        return $this->status === ProgramStatus::Active;
    }

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
