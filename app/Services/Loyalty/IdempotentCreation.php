<?php

namespace App\Services\Loyalty;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Crearea din formularele ownerului (afacere, locație, program) cu cheia de idempotency din formular:
 * un dublu-click sau o retrimitere cu aceeași cheie nu creează a doua oară (raport QA 2026-10-18, B1/B7).
 *
 * Cheia e a userului (alt user cu aceeași cheie creează separat). Lock-ul serializează cererile simultane,
 * iar rezultatul rămâne o zi în cache. Acțiunile de staff au idempotency în jurnal (`card_transactions`), nu aici.
 */
class IdempotentCreation
{
    private const TTL_HOURS = 24;

    private const LOCK_SECONDS = 10;

    /**
     * Rulează `$create` o singură dată per (user, scope, cheie) și întoarce id-ul modelului creat.
     *
     * @param  Closure(): Model  $create
     */
    public function once(User $user, string $scope, string $key, Closure $create): int
    {
        $cacheKey = "idempotency:{$scope}:{$user->id}:{$key}";

        return Cache::lock($cacheKey.':lock', self::LOCK_SECONDS)->block(self::LOCK_SECONDS, function () use ($cacheKey, $create): int {
            // Id-ul se păstrează ca text: RedisStore întoarce numerele ca string, store-ul `array` din teste ca int.
            $existing = Cache::get($cacheKey);

            if (is_string($existing) && ctype_digit($existing)) {
                return (int) $existing;
            }

            $id = (int) $create()->getKey();
            Cache::put($cacheKey, (string) $id, now()->addHours(self::TTL_HOURS));

            return $id;
        });
    }
}
