<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int|null $business_id
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property string $action
 * @property array<string, mixed>|null $payload
 */
class AuditLog extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'actor_type',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'payload',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * Jurnal append-only (ADR 013), garantat și de un trigger MySQL.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit logs are append-only.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit logs are append-only and cannot be deleted.');
        });
    }

    /**
     * Acțiunile adminului de platformă (de ex. suspendarea unei afaceri) pot să nu aibă afacere curentă.
     */
    protected function businessIsOptional(): bool
    {
        return true;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
