<?php

namespace App\Services\Loyalty;

use App\Enums\Role;
use App\Exceptions\TeamRuleViolation;
use App\Models\Location;
use App\Models\Membership;
use App\Models\User;
use App\Services\Loyalty\Concerns\RequiresOwner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Echipa afacerii = rândurile din business_user. Scoaterea din echipă șterge membership-ul, nu userul,
 * deci istoricul lui din jurnal rămâne. Afacerea are mereu cel puțin un owner (ADR 006).
 */
class TeamService
{
    use RequiresOwner;

    public function __construct(private AuditTrail $audit) {}

    public function changeRole(User $actor, Membership $membership, Role $role, ?Location $location): Membership
    {
        $this->authorizeOwner($actor, $membership->business_id);

        return DB::transaction(function () use ($actor, $membership, $role, $location): Membership {
            $owners = $this->lockOwners($membership->business_id);
            $membership = $this->lockMembership($membership);
            $previous = ['role' => $membership->role->value, 'location_id' => $membership->location_id];

            if ($membership->role === Role::Owner && $role !== Role::Owner) {
                $this->assertAnotherOwner($owners, $membership);
            }

            $membership->role = $role;
            // Ownerul lucrează în toată afacerea, nu e legat de o locație.
            $membership->location_id = $role === Role::Owner ? null : $location?->id;
            $membership->save();

            $this->audit->recordForBusiness($actor, $membership->business_id, 'member.role_changed', $membership, [
                'user_id' => $membership->user_id,
                'from' => $previous,
                'to' => ['role' => $role->value, 'location_id' => $membership->location_id],
            ]);

            return $membership;
        });
    }

    public function remove(User $actor, Membership $membership): void
    {
        $this->authorizeOwner($actor, $membership->business_id);

        DB::transaction(function () use ($actor, $membership): void {
            $owners = $this->lockOwners($membership->business_id);
            $membership = $this->lockMembership($membership);

            if ($membership->role === Role::Owner) {
                $this->assertAnotherOwner($owners, $membership);
            }

            $this->audit->recordForBusiness($actor, $membership->business_id, 'member.removed', $membership, [
                'user_id' => $membership->user_id,
                'role' => $membership->role->value,
            ]);

            $membership->delete();
        });
    }

    /**
     * Blochează ownerii afacerii mereu înaintea membrului țintă și în aceeași ordine: două retrogradări
     * simultane se serializează (fără deadlock), iar a doua vede că a rămas un singur owner.
     *
     * @return Collection<int, int>
     */
    private function lockOwners(int $businessId): Collection
    {
        return Membership::query()
            ->where('business_id', $businessId)
            ->where('role', Role::Owner)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');
    }

    /**
     * @param  Collection<int, int>  $owners
     */
    private function assertAnotherOwner(Collection $owners, Membership $membership): void
    {
        if ($owners->reject(fn (int $id) => $id === $membership->id)->isEmpty()) {
            throw new TeamRuleViolation('Afacerea trebuie să aibă cel puțin un owner.');
        }
    }

    private function lockMembership(Membership $membership): Membership
    {
        return Membership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
    }
}
