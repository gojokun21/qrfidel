<?php

namespace App\Services\Loyalty;

use App\Models\Program;

/**
 * Regula de emitere a recompenselor (SDD §5.4), comună pentru StampService și replay-ul din `cards:recalculate`.
 */
final class RewardRules
{
    /**
     * Soldul după emiterea recompenselor: fără rollover, surplusul se pierde la prima recompensă.
     *
     * @return array{int, int} [sold nou, număr de recompense emise]
     */
    public static function applyStamps(Program $program, int $balance): array
    {
        $rewards = 0;

        while ($balance >= $program->stamps_required) {
            $rewards++;
            $balance = $program->rollover_extra ? $balance - $program->stamps_required : 0;
        }

        return [$balance, $rewards];
    }
}
