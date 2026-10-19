<?php

namespace App\Services\Loyalty;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

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

    // Lock-ul trăiește mult peste o creare lentă: dacă ar expira în timpul ei, a doua cerere ar crea din nou.
    private const LOCK_SECONDS = 60;

    public function __construct(private int $waitSeconds = 15) {}

    /**
     * Rulează `$create` o singură dată per (user, scope, cheie) și întoarce id-ul modelului creat.
     *
     * @param  Closure(): Model  $create
     *
     * @throws ValidationException când aceeași cheie e încă în lucru după `waitSeconds`
     */
    public function once(User $user, string $scope, string $key, Closure $create): int
    {
        $cacheKey = $this->cacheKey($user, $scope, $key);

        try {
            return Cache::lock($cacheKey.':lock', self::LOCK_SECONDS)->block($this->waitSeconds, function () use ($cacheKey, $create): int {
                $existing = $this->stored($cacheKey);

                if ($existing !== null) {
                    return $existing;
                }

                $id = (int) $create()->getKey();
                Cache::put($cacheKey, (string) $id, now()->addHours(self::TTL_HOURS));

                return $id;
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Formularul se trimite deja. Așteaptă câteva secunde și reîncarcă pagina.',
            ]);
        }
    }

    /**
     * Id-ul creat deja cu această cheie, dacă există (de ex. ca validarea să nu respingă propria creare).
     */
    public function existing(User $user, string $scope, string $key): ?int
    {
        return $this->stored($this->cacheKey($user, $scope, $key));
    }

    private function cacheKey(User $user, string $scope, string $key): string
    {
        return "idempotency:{$scope}:{$user->id}:{$key}";
    }

    private function stored(string $cacheKey): ?int
    {
        // Id-ul se păstrează ca text: RedisStore întoarce numerele ca string, store-ul `array` din teste ca int.
        $existing = Cache::get($cacheKey);

        return is_string($existing) && ctype_digit($existing) ? (int) $existing : null;
    }
}
