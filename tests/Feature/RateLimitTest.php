<?php

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use App\Services\Loyalty\ScanTicketService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * Limitele din SDD §6 și ADR 013: limitatori numiți (cheie per angajat / client / afacere / IP),
 * nu `throttle:N,1` numeric, care împarte aceeași cheie pe IP între toate rutele.
 */

it('uses a named limiter on every throttled route', function (string $route, string $limiter) {
    expect(Route::getRoutes()->getByName($route)?->gatherMiddleware())->toContain("throttle:{$limiter}");
})->with([
    ['customer.magic.send', 'magic-link-send'],
    ['customer.magic.consume', 'magic-link-consume'],
    ['customer.enroll', 'customer-actions'],
    ['customer.counter.request', 'customer-actions'],
    ['customer.cards.qr', 'customer-qr'],
    ['customer.cards.status', 'customer-status'],
    ['staff.scan.resolve', 'staff-scan'],
    ['staff.cards.stamp', 'staff-actions'],
    ['staff.cards.redeem', 'staff-actions'],
    ['staff.requests.approve', 'staff-actions'],
    ['staff.requests.reject', 'staff-actions'],
    ['dashboard.transactions.void', 'staff-actions'],
    ['dashboard.invitations.store', 'invitations'],
    ['invitations.register', 'invitation-register'],
]);

it('has no numeric throttle left in the application routes', function () {
    $numeric = collect(Route::getRoutes()->getRoutes())
        ->reject(fn ($route) => str_starts_with((string) $route->getName(), 'password.') || str_starts_with((string) $route->getName(), 'verification.'))
        ->filter(fn ($route) => collect($route->gatherMiddleware())->contains(fn ($middleware) => is_string($middleware) && preg_match('/^throttle:\d/', $middleware)))
        ->map(fn ($route) => $route->getName() ?? $route->uri());

    expect($numeric->all())->toBe([]);
});

describe('staff actions', function () {
    beforeEach(function () {
        foreach (loyaltyFixture() as $key => $value) {
            $this->{$key} = $value;
        }
        config(['loyalty.rate_limits.staff_actions_per_minute' => 2]);

        $this->stamp = fn (User $as, array $headers = []) => $this->actingAs($as)
            ->withHeaders($headers)
            ->post(route('staff.cards.stamp', $this->card), [
                'ticket' => app(ScanTicketService::class)->issue($this->card, $as, $this->location),
                'quantity' => 1,
                'idempotency_key' => (string) Str::uuid(),
            ]);
    });

    it('limits each employee separately', function () {
        ($this->stamp)($this->staff)->assertRedirect();
        ($this->stamp)($this->staff)->assertRedirect();

        ($this->stamp)($this->staff)
            ->assertRedirect()
            ->assertSessionHasErrors(['loyalty' => 'Prea multe încercări. Încearcă din nou peste 1 min.']);

        ($this->stamp)($this->owner)->assertSessionDoesntHaveErrors('loyalty');
    });

    it('answers JSON clients with 429, a Romanian message and Retry-After', function () {
        $json = ['Accept' => 'application/json'];
        ($this->stamp)($this->staff, $json);
        ($this->stamp)($this->staff, $json);

        ($this->stamp)($this->staff, $json)
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJson(['message' => 'Prea multe încercări. Încearcă din nou peste 1 min.']);
    });
});

describe('customer actions', function () {
    beforeEach(function () {
        $this->fixture = loyaltyFixture();
        config(['loyalty.rate_limits.customer_actions_per_minute' => 1]);

        $this->enroll = fn (Customer $customer) => $this->actingAs($customer, 'customer')
            ->post(route('customer.enroll', ['business' => $this->fixture['business'], 'program' => $this->fixture['program']]));
    });

    it('limits each customer separately, not the whole IP', function () {
        $ana = Customer::factory()->create();
        $bogdan = Customer::factory()->create();

        ($this->enroll)($ana)->assertSessionDoesntHaveErrors('loyalty');
        ($this->enroll)($ana)->assertSessionHasErrors('loyalty');
        ($this->enroll)($bogdan)->assertSessionDoesntHaveErrors('loyalty');
    });
});

describe('magic link', function () {
    it('limits sending per IP', function () {
        Mail::fake();
        config(['loyalty.rate_limits.magic_link_per_minute' => 2]);

        $send = fn (string $ip, string $email) => $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from(route('customer.login'))
            ->post(route('customer.magic.send'), ['email' => $email]);

        $send('198.51.100.1', 'a@client.test')->assertSessionDoesntHaveErrors('loyalty');
        $send('198.51.100.1', 'b@client.test')->assertSessionDoesntHaveErrors('loyalty');
        $send('198.51.100.1', 'c@client.test')
            ->assertRedirect(route('customer.login'))
            ->assertSessionHasErrors('loyalty');
        $send('198.51.100.2', 'd@client.test')->assertSessionDoesntHaveErrors('loyalty');
    });
});

describe('invitations', function () {
    it('limits the invitations sent by a business, whoever sends them', function () {
        Mail::fake();
        config(['loyalty.rate_limits.invitations_per_hour' => 2]);
        $fixture = loyaltyFixture();
        $secondOwner = User::factory()->withRole($fixture['business'], Role::Owner)->create();

        $invite = fn (User $owner, string $email) => $this->actingAs($owner)
            ->post(route('dashboard.invitations.store'), ['email' => $email, 'role' => Role::Staff->value]);

        $invite($fixture['owner'], 'unu@exemplu.test')->assertSessionDoesntHaveErrors();
        $invite($fixture['owner'], 'doi@exemplu.test')->assertSessionDoesntHaveErrors();
        $invite($secondOwner, 'trei@exemplu.test')->assertSessionHasErrors('loyalty');

        $other = loyaltyFixture();
        $invite($other['owner'], 'patru@exemplu.test')->assertSessionDoesntHaveErrors();
    });
});

it('shows a Romanian 429 page for a throttled page load', function () {
    $fixture = loyaltyFixture();
    config(['loyalty.rate_limits.customer_status_per_minute' => 1]);
    $customer = $fixture['card']->customer;

    $this->actingAs($customer, 'customer')->get(route('customer.cards.status', $fixture['card']))->assertOk();

    $this->actingAs($customer, 'customer')
        ->get(route('customer.cards.status', $fixture['card']))
        ->assertStatus(429)
        ->assertSee('Prea multe încercări')
        ->assertDontSee('Too Many Requests');
});

it('shows the 429 page on Breeze forms that cannot display the loyalty error', function () {
    $user = User::factory()->unverified()->create();

    foreach (range(1, 6) as $attempt) {
        $this->actingAs($user)->from(route('verification.notice'))->post(route('verification.send'));
    }

    $this->actingAs($user)
        ->from(route('verification.notice'))
        ->post(route('verification.send'))
        ->assertStatus(429)
        ->assertSee('Prea multe încercări');
});
