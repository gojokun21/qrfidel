<?php

use App\Enums\BusinessType;
use App\Enums\ProgramStatus;
use App\Enums\Role;
use App\Exceptions\EnrollmentRefused;
use App\Exceptions\ProgramRuleViolation;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Program;
use App\Models\User;
use App\Services\Loyalty\EnrollmentService;
use App\Services\Loyalty\ProgramService;
use App\Services\Loyalty\ProgramTemplates;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->business = Business::factory()->create(['name' => 'Cafeneaua Centrală', 'slug' => 'cafeneaua-centrala', 'type' => BusinessType::Cafe]);
    $this->owner = User::factory()->withRole($this->business, Role::Owner)->create();
    $this->program = Program::factory()->for($this->business)->active()->create([
        ...ProgramTemplates::for(BusinessType::Cafe)->attributes,
        'name' => 'Cafele fidele',
    ]);
    $this->customer = Customer::factory()->create(['email' => 'ana@client.test']);
});

describe('service', function () {
    it('creates an empty card in the program business', function () {
        $result = app(EnrollmentService::class)->enroll($this->customer, $this->program);

        expect($result->created)->toBeTrue()
            ->and($result->card->business_id)->toBe($this->business->id)
            ->and($result->card->program_id)->toBe($this->program->id)
            ->and($result->card->customer_id)->toBe($this->customer->id)
            ->and($result->card->stamps_balance)->toBe(0)
            ->and($result->card->rewards_available)->toBe(0)
            ->and($result->card->secret)->toHaveLength(64)
            ->and($result->card->public_id)->not->toBeEmpty();
    });

    it('is idempotent: a second enrollment returns the existing card', function () {
        $first = app(EnrollmentService::class)->enroll($this->customer, $this->program);
        $second = app(EnrollmentService::class)->enroll($this->customer, $this->program);

        expect($second->created)->toBeFalse()
            ->and($second->card->id)->toBe($first->card->id)
            ->and(Card::query()->count())->toBe(1)
            ->and(AuditLog::query()->where('action', 'card.enrolled')->count())->toBe(1);
    });

    it('records the enrollment in the audit log with the customer as actor', function () {
        $card = app(EnrollmentService::class)->enroll($this->customer, $this->program)->card;

        $log = AuditLog::query()->where('action', 'card.enrolled')->sole();

        expect($log->business_id)->toBe($this->business->id)
            ->and($log->actor_type)->toBe($this->customer->getMorphClass())
            ->and($log->actor_id)->toBe($this->customer->id)
            ->and($log->subject_id)->toBe($card->id)
            ->and($log->payload)->toMatchArray(['card_id' => $card->id, 'program_id' => $this->program->id]);
    });

    it('refuses programs that are not active', function (ProgramStatus $status) {
        $this->program->forceFill(['status' => $status])->save();

        expect(fn () => app(EnrollmentService::class)->enroll($this->customer, $this->program))
            ->toThrow(EnrollmentRefused::class, 'Programul nu primește înscrieri acum.');

        expect(Card::query()->count())->toBe(0);
    })->with([ProgramStatus::Draft, ProgramStatus::Paused, ProgramStatus::Archived]);

    it('refuses programs of a suspended business', function () {
        $this->business->forceFill(['status' => 'suspended'])->save();

        expect(fn () => app(EnrollmentService::class)->enroll($this->customer, $this->program))
            ->toThrow(EnrollmentRefused::class, 'Afacerea nu primește înscrieri acum.');

        expect(Card::query()->count())->toBe(0);
    });

    it('checks the status of the program as stored, not the stale model', function () {
        $stale = Program::query()->findOrFail($this->program->id);
        $this->program->forceFill(['status' => ProgramStatus::Paused])->save();

        expect(fn () => app(EnrollmentService::class)->enroll($this->customer, $stale))
            ->toThrow(EnrollmentRefused::class);
    });

    it('takes a shared lock on the program (ADR 008)', function () {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        app(EnrollmentService::class)->enroll($this->customer, $this->program);

        expect(collect($queries)->contains(fn (string $sql) => str_contains($sql, 'from "programs"') && str_contains($sql, 'for share')))->toBeTrue();
    });

    it('blocks lowering stamps_required once a customer enrolled', function () {
        app(EnrollmentService::class)->enroll($this->customer, $this->program);

        expect(fn () => app(ProgramService::class)->update($this->owner, $this->program, ['stamps_required' => 3]))
            ->toThrow(ProgramRuleViolation::class);
    });

    it('lets the same customer enroll at several businesses', function () {
        $other = Program::factory()->active()->create();

        app(EnrollmentService::class)->enroll($this->customer, $this->program);
        app(EnrollmentService::class)->enroll($this->customer, $other);

        expect($this->customer->cards()->count())->toBe(2);
    });
});

describe('http', function () {
    it('enrolls a signed-in customer and shows the card 0/5 on /me', function () {
        $this->actingAs($this->customer, 'customer')
            ->post(route('customer.enroll', [$this->business, $this->program]))
            ->assertRedirect(route('customer.cards'))
            ->assertSessionHas('status', 'Te-ai înscris în „Cafele fidele”.');

        $card = Card::query()->sole();
        expect($card->customer_id)->toBe($this->customer->id)
            ->and($card->stamps_balance)->toBe(0)
            ->and($this->program->stamps_required)->toBe(5);

        $this->actingAs($this->customer, 'customer')
            ->get(route('customer.cards'))
            ->assertOk()
            ->assertSee('Cafeneaua Centrală')
            ->assertSee('Cafele fidele')
            ->assertSee('Card de fidelitate: 0 din 5 ștampile');
    });

    it('does not create a second card when the customer enrolls twice', function () {
        $this->actingAs($this->customer, 'customer')->post(route('customer.enroll', [$this->business, $this->program]));

        $this->actingAs($this->customer, 'customer')
            ->post(route('customer.enroll', [$this->business, $this->program]))
            ->assertRedirect(route('customer.cards'))
            ->assertSessionHas('status', 'Ești deja înscris în „Cafele fidele”.');

        expect(Card::query()->count())->toBe(1);
    });

    it('sends guests to the customer login', function () {
        $this->post(route('customer.enroll', [$this->business, $this->program]))
            ->assertRedirect(route('customer.login'));

        expect(Card::query()->count())->toBe(0);
    });

    it('does not treat a signed-in staff user as a customer', function () {
        $this->actingAs($this->owner)
            ->post(route('customer.enroll', [$this->business, $this->program]))
            ->assertRedirect(route('customer.login'));

        expect(Card::query()->count())->toBe(0);
    });

    it('returns 404 for a program of another business', function () {
        $foreign = Program::factory()->active()->create();

        $this->actingAs($this->customer, 'customer')
            ->post(route('customer.enroll', [$this->business, $foreign]))
            ->assertNotFound();

        expect(Card::query()->count())->toBe(0);
    });

    it('returns 404 when the business is suspended', function () {
        $this->business->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($this->customer, 'customer')
            ->post(route('customer.enroll', [$this->business, $this->program]))
            ->assertNotFound();
    });

    it('explains why an inactive program cannot be joined', function () {
        $this->program->forceFill(['status' => ProgramStatus::Paused])->save();

        $this->actingAs($this->customer, 'customer')
            ->from(route('customer.business', $this->business))
            ->post(route('customer.enroll', [$this->business, $this->program]))
            ->assertRedirect(route('customer.business', $this->business))
            ->assertSessionHasErrors(['loyalty' => 'Programul nu primește înscrieri acum.']);
    });
});
