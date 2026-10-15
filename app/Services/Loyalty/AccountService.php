<?php

namespace App\Services\Loyalty;

use App\Enums\Role;
use App\Exceptions\TeamRuleViolation;
use App\Models\Business;
use App\Models\CardTransaction;
use App\Models\Membership;
use App\Models\Reward;
use App\Models\StampRequest;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Ștergerea propriului cont de staff/owner (ADR 006): o afacere nu rămâne fără owner,
 * iar jurnalul append-only nu pierde autorul (FK restrict pe staff_user_id, redeemed_by, handled_by).
 */
class AccountService
{
    /**
     * @param  Closure(): void  $beforeDelete  rulează după verificări (de ex. logout, care salvează userul)
     */
    public function delete(User $user, Closure $beforeDelete): void
    {
        DB::transaction(function () use ($user, $beforeDelete): void {
            $ownedBusinessIds = Membership::query()
                ->where('user_id', $user->id)
                ->where('role', Role::Owner)
                ->pluck('business_id');

            // Aceleași lock-uri ca TeamService (ownerii, ordonați după id): o retrogradare sau o altă
            // ștergere simultană așteaptă, deci nu pot pleca amândoi ultimii owneri.
            $owners = Membership::query()
                ->whereIn('business_id', $ownedBusinessIds)
                ->where('role', Role::Owner)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'business_id', 'user_id']);

            $soleOwnerOf = $owners->groupBy('business_id')
                ->first(fn ($rows) => $rows->every(fn (Membership $row) => $row->user_id === $user->id))
                ?->first()?->business_id;

            if ($soleOwnerOf !== null) {
                $name = Business::query()->whereKey($soleOwnerOf)->value('name');

                throw new TeamRuleViolation("Ești singurul owner al afacerii „{$name}”. Numește alt owner înainte să-ți ștergi contul.");
            }

            if ($this->hasActivity($user)) {
                throw new TeamRuleViolation('Contul are activitate în jurnalul de fidelizare și nu poate fi șters. Cere ownerului să te scoată din echipă.');
            }

            $beforeDelete();
            $user->delete();
        });
    }

    private function hasActivity(User $user): bool
    {
        return CardTransaction::query()->withoutGlobalScopes()->where('staff_user_id', $user->id)->exists()
            || Reward::query()->withoutGlobalScopes()->where('redeemed_by', $user->id)->exists()
            || StampRequest::query()->withoutGlobalScopes()->where('handled_by', $user->id)->exists();
    }
}
