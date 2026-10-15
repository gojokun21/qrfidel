<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin === true;
    }

    /**
     * @return BelongsToMany<Business, $this, Membership, 'membership'>
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot(['id', 'role', 'location_id'])
            ->withTimestamps();
    }

    public function membershipIn(Business|int $business): ?Membership
    {
        return Membership::query()
            ->where('user_id', $this->getKey())
            ->where('business_id', $business instanceof Business ? $business->getKey() : $business)
            ->first();
    }

    public function roleIn(Business|int $business): ?Role
    {
        return $this->membershipIn($business)?->role;
    }

    public function isMemberOf(Business|int $business): bool
    {
        return $this->roleIn($business) !== null;
    }

    /**
     * Are cel puțin rolul dat în afacerea respectivă (owner ≥ manager ≥ staff).
     */
    public function hasRoleIn(Business|int $business, Role $minimum): bool
    {
        return $this->roleIn($business)?->atLeast($minimum) ?? false;
    }
}
