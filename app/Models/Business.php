<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Enums\BusinessType;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property BusinessType $type
 * @property string|null $logo
 * @property string|null $brand_color
 * @property string $timezone
 * @property BusinessStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /**
     * `status` nu e mass-assignable: doar adminul platformei suspendă o afacere.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'logo',
        'brand_color',
        'timezone',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'timezone' => 'Europe/Bucharest',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BusinessType::class,
            'status' => BusinessStatus::class,
        ];
    }

    public function isActive(): bool
    {
        return $this->status === BusinessStatus::Active;
    }

    /**
     * @return BelongsToMany<User, $this, Membership, 'membership'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot(['id', 'role', 'location_id'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * @return HasMany<Program, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
