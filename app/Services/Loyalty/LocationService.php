<?php

namespace App\Services\Loyalty;

use App\Exceptions\LocationInUse;
use App\Models\Business;
use App\Models\CardTransaction;
use App\Models\Location;
use App\Models\Reward;
use App\Models\StampRequest;
use App\Models\User;
use App\Services\Loyalty\Concerns\RequiresOwner;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Locațiile afacerii. O locație cu activitate în jurnal nu se șterge (FK restrict, istoricul rămâne).
 */
class LocationService
{
    use RequiresOwner;

    public function __construct(private AuditTrail $audit) {}

    /**
     * @param  array{name: string, address?: string|null}  $data
     */
    public function create(User $actor, Business $business, array $data): Location
    {
        $this->authorizeOwner($actor, $business);

        return DB::transaction(function () use ($actor, $business, $data): Location {
            $location = Location::query()->create([
                'business_id' => $business->id,
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
            ]);

            $this->audit->recordForBusiness($actor, $business, 'location.created', $location, ['name' => $location->name]);

            return $location;
        });
    }

    /**
     * @param  array{name: string, address?: string|null}  $data
     */
    public function update(User $actor, Location $location, array $data): Location
    {
        $this->authorizeOwner($actor, $location->business_id);

        return DB::transaction(function () use ($actor, $location, $data): Location {
            $location->fill(['name' => $data['name'], 'address' => $data['address'] ?? null]);
            $changes = array_keys($location->getDirty());
            $location->save();

            $this->audit->recordForBusiness($actor, $location->business_id, 'location.updated', $location, ['changed' => $changes]);

            return $location;
        });
    }

    public function delete(User $actor, Location $location): void
    {
        $this->authorizeOwner($actor, $location->business_id);

        if ($this->hasActivity($location)) {
            throw $this->inUse();
        }

        try {
            DB::transaction(function () use ($actor, $location): void {
                $this->audit->recordForBusiness($actor, $location->business_id, 'location.deleted', $location, ['name' => $location->name]);
                $location->delete();
            });
        } catch (QueryException $e) {
            // Activitate apărută între verificare și ștergere: FK-ul restrict o oprește.
            if ($e->getCode() === '23503') {
                throw $this->inUse();
            }

            throw $e;
        }
    }

    /**
     * Invalidează QR-ul vechi de pe tejghea (`/l/{counter_token}`).
     */
    public function regenerateCounterToken(User $actor, Location $location): Location
    {
        $this->authorizeOwner($actor, $location->business_id);

        return DB::transaction(function () use ($actor, $location): Location {
            $location->counter_token = Str::random(40);
            $location->save();

            $this->audit->recordForBusiness($actor, $location->business_id, 'location.counter_token_regenerated', $location);

            return $location;
        });
    }

    private function hasActivity(Location $location): bool
    {
        return CardTransaction::query()->withoutGlobalScopes()->where('location_id', $location->id)->exists()
            || Reward::query()->withoutGlobalScopes()->where('location_id', $location->id)->exists()
            || StampRequest::query()->withoutGlobalScopes()->where('location_id', $location->id)->exists();
    }

    private function inUse(): LocationInUse
    {
        return new LocationInUse('Locația are activitate înregistrată și nu poate fi ștearsă.');
    }
}
