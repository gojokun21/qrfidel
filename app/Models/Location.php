<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string|null $address
 * @property string $counter_token
 */
class Location extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'name',
        'address',
    ];

    protected static function booted(): void
    {
        static::creating(function (Location $location): void {
            $location->counter_token ??= Str::random(40);
        });
    }
}
