<?php

namespace App\Services\Loyalty;

use App\Exceptions\MagicLinkInvalid;
use App\Exceptions\MagicLinkThrottled;
use App\Mail\CustomerMagicLink;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Location;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Autentificarea clientului cu magic link (SDD §5.1, ADR 009), fără pachete externe:
 * URL semnat (`URL::temporarySignedRoute`) valabil 15 minute, cu un nonce consumat la prima folosire.
 * Semnătura (și deci emailul, nonce-ul și afacerea din link) e verificată de middleware-ul `signed`.
 */
class MagicLinkService
{
    /**
     * Trimite linkul. Nu spune dacă emailul are deja cont: clientul se creează abia la folosirea linkului.
     *
     * Limita din SDD §6 (5/oră per email) se numără per email + IP, ca un străin să nu poată bloca
     * login-ul cuiva doar știindu-i emailul; o limită globală mai largă per email oprește spam-ul din multe IP-uri.
     *
     * @throws MagicLinkThrottled
     */
    public function send(string $email, string $ip, ?Business $business = null, ?Location $counter = null): void
    {
        $email = Customer::normalizeEmail($email);
        $emailKey = 'magic-link:'.hash('sha256', $email);
        $limits = [
            $emailKey.':'.hash('sha256', $ip) => (int) config('loyalty.magic_link.per_email_per_hour'),
            $emailKey => (int) config('loyalty.magic_link.per_email_per_hour_all_ips'),
        ];

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw new MagicLinkThrottled(RateLimiter::availableIn($key));
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 3600);
        }

        Mail::to($email)->send(new CustomerMagicLink(
            url: $this->url($email, $business, $counter),
            business: $business,
            ttlMinutes: (int) config('loyalty.magic_link.ttl_minutes'),
        ));
    }

    /**
     * Cu `$counter` (QR-ul de pe tejghea, ADR 012), clientul se întoarce la tejghea după conectare.
     */
    public function url(string $email, ?Business $business, ?Location $counter = null): string
    {
        return URL::temporarySignedRoute(
            'customer.magic.show',
            now()->addMinutes((int) config('loyalty.magic_link.ttl_minutes')),
            array_filter([
                'email' => Customer::normalizeEmail($email),
                'nonce' => Str::random(40),
                'b' => $business?->slug,
                'l' => $counter?->counter_token,
            ]),
        );
    }

    /**
     * Folosește linkul (deja verificat de `signed`): întoarce clientul, creat acum dacă e prima lui conectare,
     * și consumă nonce-ul. Emailul e verificat prin chiar folosirea linkului.
     *
     * @throws MagicLinkInvalid
     */
    public function consume(string $email, string $nonce): Customer
    {
        if ($email === '' || $nonce === '') {
            throw new MagicLinkInvalid;
        }

        // Întâi clientul (idempotent), abia apoi nonce-ul: o eroare de DB nu arde linkul.
        $customer = DB::transaction(function () use ($email): Customer {
            $customer = Customer::query()->createOrFirst(['email' => Customer::normalizeEmail($email)]);

            if ($customer->email_verified_at === null) {
                $customer->forceFill(['email_verified_at' => now()])->save();
            }

            return $customer;
        });

        // Nonce-ul trăiește cât linkul plus un minut de toleranță; `add` e SET NX, deci câștigă o singură folosire.
        $ttl = now()->addMinutes((int) config('loyalty.magic_link.ttl_minutes') + 1);

        if (! $this->store()->add(self::nonceKey($nonce), true, $ttl)) {
            throw new MagicLinkInvalid;
        }

        return $customer;
    }

    /**
     * Linkul a fost deja folosit? Pagina de confirmare (GET) o spune de la început, fără să consume nimic.
     */
    public function wasUsed(string $nonce): bool
    {
        return $nonce !== '' && $this->store()->has(self::nonceKey($nonce));
    }

    private static function nonceKey(string $nonce): string
    {
        return 'magic-link:nonce:'.hash('sha256', $nonce);
    }

    private function store(): Repository
    {
        return Cache::store(config('loyalty.magic_link.store'));
    }
}
