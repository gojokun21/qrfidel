<?php

use App\Enums\ProgramStatus;
use App\Enums\Role;
use App\Models\Business;
use App\Models\Card;
use App\Models\Location;
use App\Models\Program;
use App\Models\User;
use App\Services\Loyalty\QrTokenService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    foreach (loyaltyFixture() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->token = fn (?Card $card = null) => app(QrTokenService::class)->issue($card ?? $this->card);

    // Angajatul fără locație fixă alege locația o dată (sesiune); la două locații nu se ghicește.
    $this->atLocation = fn (User $user, ?Location $location = null) => $this->actingAs($user)
        ->withSession(['staff_location_id' => ($location ?? $this->location)->id]);
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('access', function () {
    it('sends guests to the staff login', function () {
        $this->get(route('staff.scan'))->assertRedirect(route('login'));
        $this->post(route('staff.scan.resolve'), ['code' => 'x'])->assertRedirect(route('login'));
    });

    it('does not let a customer in', function () {
        $this->actingAs($this->card->customer, 'customer')->get(route('staff.scan'))->assertRedirect(route('login'));
    });

    it('lets every role of the business open the scanner', function (string $member) {
        ($this->atLocation)($this->{$member})
            ->get(route('staff.scan'))
            ->assertOk()
            ->assertSee('qrScanner', false)
            ->assertSee(route('staff.scan.resolve'), false);
    })->with(['staff', 'manager', 'owner']);

    it('sends a user without a business to create one', function () {
        $this->actingAs(User::factory()->create())->get(route('staff.scan'))->assertRedirect(route('dashboard.business.create'));
    });
});

describe('location', function () {
    it('asks a staff member without a fixed location to choose one when there are several', function () {
        $this->actingAs($this->staff)
            ->get(route('staff.scan'))
            ->assertSee('Alege locația')
            ->assertSee($this->location->name)
            ->assertSee($this->otherLocation->name)
            ->assertDontSee('qrScanner', false);

        $this->actingAs($this->staff)
            ->post(route('staff.scan.resolve'), ['code' => ($this->token)()->token])
            ->assertSessionHasErrors(['loyalty' => 'Alege locația în care lucrezi.']);
    });

    it('remembers the chosen location', function () {
        $this->actingAs($this->staff)
            ->post(route('staff.location'), ['location_id' => $this->otherLocation->id])
            ->assertRedirect(route('staff.scan'))
            ->assertSessionHas('staff_location_id', $this->otherLocation->id);
    });

    it('refuses a location of another business', function () {
        $foreign = Location::factory()->create();

        $this->actingAs($this->staff)
            ->post(route('staff.location'), ['location_id' => $foreign->id])
            ->assertSessionHasErrors('location_id')
            ->assertSessionMissing('staff_location_id');
    });

    it('uses the fixed location of a bound member and does not let them choose another', function () {
        $this->actingAs($this->manager)
            ->get(route('staff.scan'))
            ->assertSee($this->location->name)
            ->assertDontSee('Alege locația');

        $this->actingAs($this->manager)
            ->post(route('staff.location'), ['location_id' => $this->otherLocation->id])
            ->assertSessionHasErrors('location_id');
    });

    it('picks the only location automatically', function () {
        $business = Business::factory()->create();
        $location = Location::factory()->for($business)->create();
        $program = Program::factory()->for($business)->active()->create();
        $card = Card::factory()->for($program)->create();
        $staff = User::factory()->withRole($business, Role::Staff)->create();

        $this->actingAs($staff)
            ->post(route('staff.scan.resolve'), ['code' => ($this->token)($card)->token])
            ->assertSessionHasNoErrors()
            ->assertRedirectContains(route('staff.cards.show', $card));

        expect($location->exists)->toBeTrue();
    });

    it('explains that a business without locations cannot scan yet', function () {
        $business = Business::factory()->create();
        $owner = User::factory()->withRole($business, Role::Owner)->create();

        $this->actingAs($owner)->get(route('staff.scan'))->assertOk()->assertSee('Adaugă întâi o locație');
    });
});

describe('resolve', function () {
    it('resolves a scanned QR and opens the card screen with a fresh ticket', function () {
        $response = ($this->atLocation)($this->staff)
            ->post(route('staff.scan.resolve'), ['code' => ($this->token)()->token])
            ->assertSessionHasNoErrors();

        expect($response->headers->get('Location'))
            ->toStartWith(route('staff.cards.show', $this->card).'?ticket=');
    });

    it('answers the camera scanner with JSON', function () {
        ($this->atLocation)($this->staff)
            ->postJson(route('staff.scan.resolve'), ['code' => ($this->token)()->token])
            ->assertOk()
            ->assertJsonPath('redirect', fn (string $url) => str_starts_with($url, route('staff.cards.show', $this->card).'?ticket='));
    });

    it('resolves a typed short code, with spaces and lower case', function () {
        $code = ($this->token)()->shortCode;
        $typed = strtolower(substr($code, 0, 3).' '.substr($code, 3));

        ($this->atLocation)($this->staff)
            ->post(route('staff.scan.resolve'), ['code' => $typed])
            ->assertSessionHasNoErrors()
            ->assertRedirectContains(route('staff.cards.show', $this->card));
    });

    it('refuses an invalid, expired or reused QR with a clear message', function () {
        Carbon::setTestNow('2026-10-16 12:00:00');
        $token = ($this->token)()->token;

        ($this->atLocation)($this->staff)->post(route('staff.scan.resolve'), ['code' => $token.'x'])
            ->assertSessionHasErrors(['loyalty' => 'Cod QR invalid.']);

        ($this->atLocation)($this->staff)->post(route('staff.scan.resolve'), ['code' => $token])->assertSessionHasNoErrors();
        ($this->atLocation)($this->staff)->post(route('staff.scan.resolve'), ['code' => $token])
            ->assertSessionHasErrors(['loyalty' => 'Cod QR deja folosit.']);

        $old = ($this->token)()->token;
        Carbon::setTestNow(now()->addSeconds(91));
        ($this->atLocation)($this->staff)->postJson(route('staff.scan.resolve'), ['code' => $old])
            ->assertUnprocessable()
            ->assertJson(['message' => 'Cod QR expirat – cere clientului să-l reîmprospăteze.']);
    });

    it('requires a code', function () {
        ($this->atLocation)($this->staff)->post(route('staff.scan.resolve'), ['code' => ''])->assertSessionHasErrors('code');
    });

    it('refuses the card of another business', function () {
        $foreign = Card::factory()->create();

        ($this->atLocation)($this->staff)
            ->postJson(route('staff.scan.resolve'), ['code' => ($this->token)($foreign)->token])
            ->assertForbidden();
    });

    it('refuses a stopped program without rewards, but lets a card with rewards through', function () {
        $this->program->update(['status' => ProgramStatus::Paused]);

        ($this->atLocation)($this->staff)->post(route('staff.scan.resolve'), ['code' => ($this->token)()->token])
            ->assertSessionHasErrors(['loyalty' => 'Programul nu este activ.']);

        giveReward($this->card);

        ($this->atLocation)($this->staff)->post(route('staff.scan.resolve'), ['code' => ($this->token)()->token])
            ->assertSessionHasNoErrors()
            ->assertRedirectContains(route('staff.cards.show', $this->card));
    });

    it('limits a staff member to 30 scans per minute', function () {
        foreach (range(1, 30) as $i) {
            ($this->atLocation)($this->staff)->postJson(route('staff.scan.resolve'), ['code' => 'AAAAAA'])->assertUnprocessable();
        }

        ($this->atLocation)($this->staff)->postJson(route('staff.scan.resolve'), ['code' => 'AAAAAA'])->assertTooManyRequests();

        // Limita e per angajat, nu comună.
        ($this->atLocation)($this->owner)->postJson(route('staff.scan.resolve'), ['code' => 'AAAAAA'])->assertUnprocessable();
    });
});
