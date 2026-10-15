<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Invitație în echipa unei afaceri. Tokenul în clar e doar în email; aici stă hash-ul lui.
 *
 * @property int $id
 * @property int $business_id
 * @property string $email
 * @property Role $role
 * @property int|null $location_id
 * @property string $token_hash
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property int|null $accepted_by
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 */
class Invitation extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'email',
        'role',
        'location_id',
        'token_hash',
        'invited_by',
        'expires_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isRevoked() && ! $this->isExpired();
    }

    /**
     * @return array<string, class-string<Model>>
     */
    protected function businessParents(): array
    {
        return [
            'location_id' => Location::class,
        ];
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
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
