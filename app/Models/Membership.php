<?php

namespace App\Models;

use App\Enums\Role;
use App\Exceptions\TenancyViolation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Rolul unui user (staff/admin) într-o afacere; location_id leagă managerul/angajatul de o locație.
 *
 * @property int $id
 * @property int $business_id
 * @property int $user_id
 * @property Role $role
 * @property int|null $location_id
 */
class Membership extends Pivot
{
    protected $table = 'business_user';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Membership $membership): void {
            if ($membership->location_id === null) {
                return;
            }

            $locationBusinessId = Location::query()->withoutGlobalScopes()->whereKey($membership->location_id)->value('business_id');

            if ($locationBusinessId !== null && (int) $locationBusinessId !== (int) $membership->business_id) {
                throw new TenancyViolation('The membership location belongs to another business.');
            }
        });
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
