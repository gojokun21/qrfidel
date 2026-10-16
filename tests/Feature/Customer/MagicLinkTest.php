<?php

use App\Mail\CustomerMagicLink;
use App\Models\Business;
use App\Models\Customer;
use App\Services\Loyalty\MagicLinkService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->business = Business::factory()->create(['name' => 'Cafeneaua Centrală', 'slug' => 'cafeneaua-centrala']);
});

/**
 * Cere un magic link prin formular și întoarce URL-ul din email.
 */
function requestMagicLink(string $email, ?string $slug = null): string
{
    test()->post(route('customer.magic.send'), array_filter(['email' => $email, 'business' => $slug]))
        ->assertSessionHasNoErrors();

    $url = null;
    Mail::assertSent(CustomerMagicLink::class, function (CustomerMagicLink $mail) use (&$url, $email) {
        $url = $mail->url;

        return $mail->hasTo(mb_strtolower(trim($email)));
    });

    return $url;
}

describe('requesting a link', function () {
    it('shows the email form to guests', function () {
        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee('Primește linkul de conectare');
    });

    it('emails a signed link valid for 15 minutes', function () {
        $this->from(route('customer.login'))
            ->post(route('customer.magic.send'), ['email' => 'ana@client.test'])
            ->assertRedirect(route('customer.login'))
            ->assertSessionHas('magic_link_sent', 'ana@client.test');

        Mail::assertSent(CustomerMagicLink::class, function (CustomerMagicLink $mail) {
            parse_str((string) parse_url($mail->url, PHP_URL_QUERY), $query);

            return $mail->hasTo('ana@client.test')
                && str_starts_with($mail->url, route('customer.magic.show'))
                && isset($query['signature'], $query['nonce'])
                && (int) $query['expires'] === now()->addMinutes(15)->getTimestamp();
        });
    });

    it('does not create the customer before the link is used', function () {
        requestMagicLink('ana@client.test');

        expect(Customer::query()->count())->toBe(0);
    });

    it('answers the same way for known and unknown emails', function () {
        Customer::factory()->create(['email' => 'ana@client.test']);

        $this->from(route('customer.login'))->post(route('customer.magic.send'), ['email' => 'ana@client.test'])
            ->assertRedirect(route('customer.login'))->assertSessionHas('magic_link_sent', 'ana@client.test');
        $this->from(route('customer.login'))->post(route('customer.magic.send'), ['email' => 'nou@client.test'])
            ->assertRedirect(route('customer.login'))->assertSessionHas('magic_link_sent', 'nou@client.test');
        Mail::assertSentCount(2);
    });

    it('normalizes the email', function () {
        $this->post(route('customer.magic.send'), ['email' => '  Ana@Client.TEST ']);

        Mail::assertSent(CustomerMagicLink::class, fn (CustomerMagicLink $mail) => $mail->hasTo('ana@client.test'));
    });

    it('rejects an invalid email', function (string $email) {
        $this->post(route('customer.magic.send'), ['email' => $email])
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    })->with(['', 'nu-e-email', str_repeat('a', 250).'@client.test']);

    it('sends at most 5 links per hour to the same email', function () {
        foreach (range(1, 5) as $i) {
            $this->post(route('customer.magic.send'), ['email' => 'ana@client.test'])->assertSessionHasNoErrors();
        }

        $this->post(route('customer.magic.send'), ['email' => 'ANA@client.test'])
            ->assertSessionHasErrors(['loyalty']);

        Mail::assertSentCount(5);

        $this->travel(61)->minutes();

        $this->post(route('customer.magic.send'), ['email' => 'ana@client.test'])->assertSessionHasNoErrors();
        Mail::assertSentCount(6);
    });

    it('does not let a stranger lock someone out from another IP', function () {
        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
                ->post(route('customer.magic.send'), ['email' => 'ana@client.test']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->post(route('customer.magic.send'), ['email' => 'ana@client.test'])
            ->assertSessionHasNoErrors();
        Mail::assertSentCount(6);
    });

    it('caps the links per email across all IPs', function () {
        config(['loyalty.magic_link.per_email_per_hour_all_ips' => 7]);

        foreach (range(1, 7) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
                ->post(route('customer.magic.send'), ['email' => 'ana@client.test'])
                ->assertSessionHasNoErrors();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->post(route('customer.magic.send'), ['email' => 'ana@client.test'])
            ->assertSessionHasErrors(['loyalty']);
        Mail::assertSentCount(7);
    });

    it('does not let one email exhaust the limit of another', function () {
        foreach (range(1, 5) as $i) {
            $this->post(route('customer.magic.send'), ['email' => 'ana@client.test']);
        }

        $this->post(route('customer.magic.send'), ['email' => 'bogdan@client.test'])->assertSessionHasNoErrors();
    });

    it('keeps the business slug in the link', function () {
        $url = requestMagicLink('ana@client.test', 'cafeneaua-centrala');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        expect($query['b'])->toBe('cafeneaua-centrala');
    });

    it('ignores an unknown business slug', function () {
        $url = requestMagicLink('ana@client.test', 'nu-exista');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        expect($query)->not->toHaveKey('b');
    });
});

describe('using the link', function () {
    it('shows a confirmation page on GET without signing in or using the link', function () {
        $url = requestMagicLink('ana@client.test');

        $this->get($url)
            ->assertOk()
            ->assertSee('Continuă')
            ->assertSee('ana@client.test')
            ->assertSee('action="'.e($url).'"', false);

        $this->assertGuest('customer');
        expect(Customer::query()->count())->toBe(0);

        // GET-ul (de ex. un scanner de linkuri din clientul de email) nu consumă linkul.
        $this->post($url)->assertRedirect(route('customer.cards'));
        $this->assertAuthenticated('customer');
    });

    it('signs in on the customer guard, creates the customer and remembers the session', function () {
        $url = requestMagicLink('ana@client.test');

        $response = $this->post($url)->assertRedirect(route('customer.cards'));

        $customer = Customer::query()->sole();
        expect($customer->email)->toBe('ana@client.test')
            ->and($customer->email_verified_at)->not->toBeNull();

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
        $response->assertCookie(Auth::guard('customer')->getRecallerName());
    });

    it('signs in an existing customer without creating another one', function () {
        $customer = Customer::factory()->create(['email' => 'ana@client.test', 'email_verified_at' => null]);

        $this->post(requestMagicLink('Ana@Client.test'));

        $this->assertAuthenticatedAs($customer, 'customer');
        expect(Customer::query()->count())->toBe(1)
            ->and($customer->fresh()->email_verified_at)->not->toBeNull();
    });

    it('returns to the business page when the link was requested there', function () {
        $this->post(requestMagicLink('ana@client.test', 'cafeneaua-centrala'))
            ->assertRedirect(route('customer.business', $this->business));
    });

    it('falls back to the cards page when the business was suspended meanwhile', function () {
        $url = requestMagicLink('ana@client.test', 'cafeneaua-centrala');
        $this->business->forceFill(['status' => 'suspended'])->save();

        $this->post($url)->assertRedirect(route('customer.cards'));
    });

    it('works only once', function () {
        $url = requestMagicLink('ana@client.test');

        $this->post($url)->assertRedirect(route('customer.cards'));
        Auth::guard('customer')->logout();

        $this->post($url)
            ->assertForbidden()
            ->assertSee('Linkul nu mai e valabil');
        $this->assertGuest('customer');
    });

    it('expires after 15 minutes', function () {
        $url = requestMagicLink('ana@client.test');

        $this->travel(16)->minutes();

        $this->get($url)->assertForbidden()->assertSee('Linkul nu mai e valabil');
        $this->post($url)->assertForbidden();
        $this->assertGuest('customer');
    });

    it('rejects a link whose email was changed', function () {
        $url = requestMagicLink('ana@client.test');
        $forged = str_replace(urlencode('ana@client.test'), urlencode('bogdan@client.test'), $url);

        expect($forged)->not->toBe($url);

        $this->post($forged)->assertForbidden()->assertSee('Linkul nu mai e valabil');
        $this->assertGuest('customer');
        expect(Customer::query()->count())->toBe(0);
    });

    it('ignores body fields that try to override the signed email and nonce', function () {
        $url = requestMagicLink('atacator@client.test');
        $victim = Customer::factory()->create(['email' => 'victima@client.test']);

        $this->post($url, ['email' => 'victima@client.test', 'nonce' => str_repeat('x', 40), 'b' => 'cafeneaua-centrala'])
            ->assertRedirect(route('customer.cards'));

        $this->assertAuthenticatedAs(Customer::query()->where('email', 'atacator@client.test')->sole(), 'customer');
        expect(auth('customer')->id())->not->toBe($victim->id);

        // Nonce-ul semnat a fost consumat: linkul nu mai merge, oricum ar arăta corpul cererii.
        Auth::guard('customer')->logout();
        $this->post($url, ['nonce' => str_repeat('y', 40)])->assertForbidden();
        $this->assertGuest('customer');
    });

    it('keeps used nonces outside the cache cleared by cache:clear', function () {
        // În producție store-ul e `loyalty` (în teste `array`): Redis pe conexiunea `default`, nu pe cea a cache-ului.
        expect(config('cache.stores.loyalty.driver'))->toBe('redis')
            ->and(config('cache.stores.loyalty.connection'))->toBe('default')
            ->and(config('cache.stores.loyalty.connection'))->not->toBe(config('cache.stores.redis.connection'));
    });

    it('rejects a link whose business was changed', function () {
        Business::factory()->create(['slug' => 'alta-afacere']);
        $url = requestMagicLink('ana@client.test', 'cafeneaua-centrala');

        $this->post(str_replace('b=cafeneaua-centrala', 'b=alta-afacere', $url))->assertForbidden();
        $this->assertGuest('customer');
    });

    it('rejects a link without a signature', function () {
        $this->post(route('customer.magic.consume', ['email' => 'ana@client.test', 'nonce' => str_repeat('a', 40)]))
            ->assertForbidden();

        $this->assertGuest('customer');
    });

    it('a used link of one email does not affect another link', function () {
        $first = requestMagicLink('ana@client.test');
        Mail::fake();
        $second = requestMagicLink('ana@client.test');

        $this->post($first);
        Auth::guard('customer')->logout();

        $this->post($second)->assertRedirect(route('customer.cards'));
        $this->assertAuthenticated('customer');
    });
});

describe('service', function () {
    it('builds links only for normalized emails', function () {
        $url = app(MagicLinkService::class)->url('  Ana@Client.TEST ', null);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        expect($query['email'])->toBe('ana@client.test');
    });
});
