<?php

namespace App\Services\Loyalty;

use App\Enums\Role;
use App\Exceptions\InvalidQrToken;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Program;
use App\Models\User;
use App\Services\Loyalty\Concerns\GuardsCardActions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * QR-ul rotativ al cardului (SDD §5.2, §6, ADR 005): `public_id.timestamp.jti.semnătură`, semnat HMAC-SHA256
 * cu secretul cardului, valabil 90 s și de unică folosință (jti păstrat 5 minute în Redis).
 */
class QrTokenService
{
    use GuardsCardActions;

    /** Intră în semnătură, ca un format viitor să nu poată fi confundat cu acesta. */
    private const VERSION = 'qr1';

    /** Fără 0/O și 1/I/L: codul scurt se dictează și se tastează. */
    private const CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const CODE_LENGTH = 6;

    private const TOKEN_PATTERN = '/^([0-9A-Za-z]{26})\.(\d{1,12})\.([A-Za-z0-9_-]{16})\.([A-Za-z0-9_-]{43})$/';

    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * Emite un token nou (PWA-ul cere unul la fiecare rotație) și un cod scurt valabil cât tokenul.
     * Un card are un singur cod scurt activ: cel emis la rotația anterioară e revocat.
     */
    public function issue(Card $card): QrToken
    {
        $issuedAt = now()->toImmutable()->startOfSecond();
        $ttl = $this->ttl();

        $payload = $card->public_id.'.'.$issuedAt->getTimestamp().'.'.self::base64Url(random_bytes(12));
        $token = $payload.'.'.$this->sign($card, $payload);

        return new QrToken(
            token: $token,
            shortCode: $this->storeShortCode($card, $token, $ttl),
            issuedAt: $issuedAt,
            expiresAt: $issuedAt->addSeconds($ttl),
        );
    }

    /**
     * QR-ul cerut de PWA-ul clientului (ADR 010): doar pentru cardul lui, și doar cât poate fi scanat
     * (afacere și program active); altfel clientul ar arăta un cod pe care `resolve()` îl refuză.
     *
     * @throws AuthorizationException
     * @throws InvalidQrToken
     */
    public function issueForCustomer(Card $card, Customer $customer): QrToken
    {
        // Aceeași regulă (și același 404) ca pagina cardului: CardPolicy::own.
        Gate::forUser($customer)->authorize('own', $card);

        $this->assertActive($card);

        return $this->issue($card);
    }

    /**
     * Cardul poate fi arătat la casă acum (afacere și program active)? Pagina cardului și emiterea folosesc aceeași regulă.
     */
    public function isScannable(Card $card): bool
    {
        try {
            $this->assertActive($card);

            return true;
        } catch (InvalidQrToken) {
            return false;
        }
    }

    /**
     * Verifică tokenul (sau codul scurt) scanat la locația dată și îl consumă. Consumul jti e ultimul pas,
     * ca un scan refuzat (locație greșită, program pe pauză) să nu ardă codul clientului.
     *
     * @throws InvalidQrToken
     * @throws AuthorizationException
     */
    public function resolve(string $input, User $actor, Location $location): Card
    {
        $input = trim($input);
        $shortCodeKey = null;

        if (! str_contains($input, '.')) {
            $shortCodeKey = $this->shortCodeKey($location->business_id, $this->normalizeShortCode($input));
            $stored = $this->store()->get($shortCodeKey);
            $input = is_string($stored) ? $stored : throw InvalidQrToken::unknownShortCode();
        }

        if (preg_match(self::TOKEN_PATTERN, $input, $parts) !== 1) {
            throw InvalidQrToken::invalid();
        }

        [, $publicId, $timestamp, $jti, $signature] = $parts;

        // Card inexistent și semnătură greșită dau același răspuns, cu aproximativ același cost (secret criptat
        // + HMAC pe un card fictiv): nici mesajul, nici timpul de răspuns nu dezvăluie ce carduri există.
        $card = Card::query()->withoutGlobalScopes()->where('public_id', $publicId)->first();
        $signer = $card ?? tap(new Card, fn (Card $dummy) => $dummy->secret = Card::newSecret());

        if (! hash_equals($this->sign($signer, "{$publicId}.{$timestamp}.{$jti}"), $signature) || $card === null) {
            throw InvalidQrToken::invalid();
        }

        $age = now()->getTimestamp() - (int) $timestamp;

        if ($age < -(int) config('loyalty.qr.clock_skew_seconds')) {
            throw InvalidQrToken::invalid();
        }

        if ($age > $this->ttl()) {
            throw InvalidQrToken::expired();
        }

        if ($location->business_id !== $card->business_id) {
            throw new AuthorizationException('Locația nu aparține afacerii acestui card.');
        }

        $this->authorizeMember($actor, $card, Role::Staff, $location);
        $this->assertActive($card);

        // add() e atomic (SET NX în Redis): din două scanări simultane ale aceluiași cod, doar una trece.
        if (! $this->store()->add("qr:jti:{$jti}", $card->id, $this->usedTtl())) {
            throw InvalidQrToken::alreadyUsed();
        }

        if ($shortCodeKey !== null) {
            $this->store()->forget($shortCodeKey);
        }

        return $card;
    }

    /**
     * Secret nou: toate tokenurile și codurile scurte emise până acum devin invalide (telefon pierdut, delogare).
     * Fără actor = clientul însuși (zona client); un actor de staff trebuie să fie cel puțin manager.
     *
     * @throws AuthorizationException
     */
    public function rotateSecret(Card $card, ?User $actor = null): void
    {
        if ($actor !== null) {
            $this->authorizeMember($actor, $card, Role::Manager);
        }

        DB::transaction(function () use ($card, $actor): void {
            $card->secret = Card::newSecret();
            $card->save();

            if ($actor !== null) {
                $this->audit->record($actor, $card, 'card.secret_rotated', $card);
            }
        });
    }

    private function assertActive(Card $card): void
    {
        if (! $card->business->isActive()) {
            throw new InvalidQrToken('Afacerea este suspendată.');
        }

        // Fără scope: contextul curent poate fi altă afacere; apartenența a fost verificată mai sus.
        $program = Program::query()->withoutGlobalScopes()->findOrFail($card->program_id);
        $card->setRelation('program', $program);

        if (! $program->isActive()) {
            throw new InvalidQrToken('Programul nu este activ.');
        }
    }

    private function sign(Card $card, string $payload): string
    {
        return self::base64Url(hash_hmac('sha256', self::VERSION.'|'.$payload, $card->secret, true));
    }

    private function storeShortCode(Card $card, string $token, int $ttl): string
    {
        $max = strlen(self::CODE_ALPHABET) - 1;
        $currentKey = "qr:code-of:{$card->id}";

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, $max)];
            }

            $key = $this->shortCodeKey($card->business_id, $code);

            if ($this->store()->add($key, $token, $ttl)) {
                $this->retirePreviousShortCode($currentKey);
                $this->store()->put($currentKey, $key, $ttl);

                return $code;
            }
        }

        throw new RuntimeException('Nu am putut genera un cod scurt unic.');
    }

    /**
     * Codul anterior mai trăiește câteva secunde (ADR 010): clientul poate fi în mijlocul dictării când PWA-ul rotește.
     * Nu trăiește niciodată mai mult decât tokenul lui: grația se taie la timpul rămas din valabilitate.
     */
    private function retirePreviousShortCode(string $currentKey): void
    {
        $previousKey = $this->store()->get($currentKey);
        if (! is_string($previousKey)) {
            return;
        }

        $previousToken = $this->store()->get($previousKey);
        $issuedAt = is_string($previousToken) ? (int) (explode('.', $previousToken)[1] ?? 0) : 0;
        $remaining = $issuedAt + $this->ttl() - now()->getTimestamp();
        $grace = min((int) config('loyalty.qr.short_code_grace_seconds'), $remaining);

        if ($grace > 0 && is_string($previousToken)) {
            $this->store()->put($previousKey, $previousToken, $grace);
        } else {
            $this->store()->forget($previousKey);
        }
    }

    /**
     * Codul se tastează: acceptă litere mici, spații și cratime.
     */
    private function normalizeShortCode(string $input): string
    {
        $code = strtoupper(str_replace([' ', '-'], '', $input));
        $pattern = '/^['.self::CODE_ALPHABET.']{'.self::CODE_LENGTH.'}$/';

        return preg_match($pattern, $code) === 1 ? $code : throw InvalidQrToken::invalid();
    }

    /**
     * Codurile scurte sunt per afacere: un cod nu se rezolvă la locația altei afaceri.
     */
    private function shortCodeKey(int $businessId, string $code): string
    {
        return "qr:code:{$businessId}:{$code}";
    }

    private function ttl(): int
    {
        return (int) config('loyalty.qr.ttl_seconds');
    }

    /**
     * jti-ul se păstrează cel puțin cât poate fi acceptat tokenul, altfel un QR consumat ar putea fi refolosit.
     */
    private function usedTtl(): int
    {
        return max((int) config('loyalty.qr.used_ttl_seconds'), $this->ttl() + (int) config('loyalty.qr.clock_skew_seconds'));
    }

    private function store(): Repository
    {
        return Cache::store(config('loyalty.qr.store'));
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
