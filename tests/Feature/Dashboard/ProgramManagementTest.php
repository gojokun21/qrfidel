<?php

use App\Enums\BusinessType;
use App\Enums\ProgramStatus;
use App\Enums\Role;
use App\Enums\StampIcon;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Card;
use App\Models\Program;
use App\Models\User;
use App\Services\Loyalty\ProgramTemplates;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->business = Business::factory()->create(['name' => 'Cafeneaua Centrală', 'type' => BusinessType::Cafe]);
    $this->owner = User::factory()->withRole($this->business, Role::Owner)->create();
});

/**
 * Ce trimite wizard-ul după ce ownerul alege template-ul „Cafenea” și apasă „Publică”.
 */
function cafeWizardPayload(array $overrides = []): array
{
    return [
        'template' => 'cafe',
        ...ProgramTemplates::for(BusinessType::Cafe)->attributes,
        'rollover_extra' => '0',
        'color' => '#7c2d12',
        'intent' => 'publish',
        'idempotency_key' => (string) Str::uuid(),
        ...$overrides,
    ];
}

describe('list', function () {
    it('lists only the programs of the current business', function () {
        Program::factory()->for($this->business)->active()->create(['name' => 'Cafele fidele']);
        Program::factory()->create(['name' => 'Program străin']);

        $this->actingAs($this->owner)
            ->get(route('dashboard.programs.index'))
            ->assertOk()
            ->assertSee('Cafele fidele')
            ->assertDontSee('Program străin');
    });

    it('invites the owner to create the first program', function () {
        $this->actingAs($this->owner)
            ->get(route('dashboard.programs.index'))
            ->assertOk()
            ->assertSee('Creează primul program')
            ->assertSee('href="'.route('dashboard.programs.create').'"', false);
    });

    it('lets managers see the programs but not change them', function () {
        $program = Program::factory()->for($this->business)->active()->create(['name' => 'Cafele fidele']);
        $manager = User::factory()->withRole($this->business, Role::Manager)->create();

        $this->actingAs($manager)->get(route('dashboard.programs.index'))
            ->assertOk()
            ->assertSee('Cafele fidele')
            ->assertDontSee('href="'.route('dashboard.programs.edit', $program).'"', false);

        $this->actingAs($manager)->get(route('dashboard.programs.create'))->assertForbidden();
        $this->actingAs($manager)->post(route('dashboard.programs.store'), cafeWizardPayload())->assertForbidden();
        $this->actingAs($manager)->get(route('dashboard.programs.edit', $program))->assertForbidden();
        $this->actingAs($manager)->patch(route('dashboard.programs.update', $program), cafeWizardPayload())->assertForbidden();
        $this->actingAs($manager)->post(route('dashboard.programs.status', $program), ['status' => 'paused'])->assertForbidden();
        $this->actingAs($manager)->post(route('dashboard.programs.duplicate', $program))->assertForbidden();
        $this->actingAs($manager)->delete(route('dashboard.programs.destroy', $program))->assertForbidden();

        expect(Program::query()->count())->toBe(1);
    });

    it('forbids staff from the programs pages', function () {
        $staff = User::factory()->withRole($this->business, Role::Staff)->create();

        $this->actingAs($staff)->get(route('dashboard.programs.index'))->assertForbidden();
    });

    it('hides the programs of other businesses behind a 404', function () {
        $foreign = Program::factory()->create();

        $this->actingAs($this->owner)->get(route('dashboard.programs.edit', $foreign))->assertNotFound();
        $this->actingAs($this->owner)->patch(route('dashboard.programs.update', $foreign), cafeWizardPayload())->assertNotFound();
        $this->actingAs($this->owner)->post(route('dashboard.programs.status', $foreign), ['status' => 'paused'])->assertNotFound();
        $this->actingAs($this->owner)->delete(route('dashboard.programs.destroy', $foreign))->assertNotFound();
    });
});

describe('wizard', function () {
    it('preselects the template of the business type', function () {
        $this->actingAs($this->owner)
            ->get(route('dashboard.programs.create'))
            ->assertOk()
            ->assertSee('Tip și template')
            ->assertSee('Reguli')
            ->assertSee('Aspect')
            ->assertSee('data-template="cafe" aria-pressed="true"', false)
            ->assertSee('5 cafele = 1 gratis');
    });

    it('creates an active program from the cafe template', function () {
        $this->actingAs($this->owner)
            ->post(route('dashboard.programs.store'), cafeWizardPayload())
            ->assertRedirect(route('dashboard.programs.index'))
            ->assertSessionHasNoErrors();

        $program = Program::query()->sole();

        expect($program->business_id)->toBe($this->business->id)
            ->and($program->status)->toBe(ProgramStatus::Active)
            ->and($program->name)->toBe('5 cafele = 1 gratis')
            ->and($program->stamps_required)->toBe(5)
            ->and($program->reward_title)->toBe('Cafea gratis')
            ->and($program->cooldown_minutes)->toBe(30)
            ->and($program->daily_limit)->toBe(1)
            ->and($program->rollover_extra)->toBeFalse()
            ->and($program->stamp_icon)->toBe(StampIcon::Coffee)
            ->and($program->color)->toBe('#7c2d12');

        expect(AuditLog::query()->where('action', 'program.created')->sole()->payload)
            ->toMatchArray(['template' => 'cafe', 'status' => 'active']);
    });

    it('saves the program as a draft', function () {
        $this->actingAs($this->owner)
            ->post(route('dashboard.programs.store'), cafeWizardPayload(['intent' => 'draft']))
            ->assertRedirect(route('dashboard.programs.index'));

        expect(Program::query()->sole()->status)->toBe(ProgramStatus::Draft);
    });

    it('accepts empty optional limits as unlimited', function () {
        $this->actingAs($this->owner)
            ->post(route('dashboard.programs.store'), cafeWizardPayload([
                'daily_limit' => '',
                'stamps_expire_days' => '',
                'reward_expire_days' => '',
                'color' => '',
            ]))
            ->assertSessionHasNoErrors();

        $program = Program::query()->sole();

        expect($program->daily_limit)->toBeNull()
            ->and($program->stamps_expire_days)->toBeNull()
            ->and($program->reward_expire_days)->toBeNull()
            ->and($program->color)->toBeNull();
    });

    it('validates the program fields', function (array $overrides, string $field) {
        $this->actingAs($this->owner)
            ->from(route('dashboard.programs.create'))
            ->post(route('dashboard.programs.store'), cafeWizardPayload($overrides))
            ->assertRedirect(route('dashboard.programs.create'))
            ->assertSessionHasErrors($field);

        expect(Program::query()->count())->toBe(0);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'zero stamps' => [['stamps_required' => 0], 'stamps_required'],
        'too many stamps' => [['stamps_required' => 51], 'stamps_required'],
        'more per visit than required' => [['stamps_required' => 3, 'max_stamps_per_visit' => 4], 'max_stamps_per_visit'],
        'negative cooldown' => [['cooldown_minutes' => -1], 'cooldown_minutes'],
        'daily limit below a visit' => [['max_stamps_per_visit' => 2, 'daily_limit' => 1], 'daily_limit'],
        'zero expiry' => [['reward_expire_days' => 0], 'reward_expire_days'],
        'missing reward' => [['reward_title' => ''], 'reward_title'],
        'invalid color' => [['color' => 'red'], 'color'],
        'unknown icon' => [['stamp_icon' => 'rocket'], 'stamp_icon'],
        'unknown template' => [['template' => 'barber-shop'], 'template'],
        'unknown intent' => [['intent' => 'launch'], 'intent'],
    ]);
});

describe('edit', function () {
    it('shows the wizard filled with the program', function () {
        $program = Program::factory()->for($this->business)->active()->create(['name' => 'Cafele fidele', 'reward_title' => 'Croissant']);

        $this->actingAs($this->owner)
            ->get(route('dashboard.programs.edit', $program))
            ->assertOk()
            ->assertSee('Cafele fidele')
            ->assertSee('Croissant');
    });

    it('updates the program', function () {
        $program = Program::factory()->for($this->business)->active()->create();

        $this->actingAs($this->owner)
            ->patch(route('dashboard.programs.update', $program), cafeWizardPayload(['name' => 'Cafele fidele', 'stamps_required' => 6]))
            ->assertRedirect(route('dashboard.programs.index'))
            ->assertSessionHasNoErrors();

        expect($program->fresh())
            ->name->toBe('Cafele fidele')
            ->stamps_required->toBe(6)
            ->status->toBe(ProgramStatus::Active);
    });

    it('explains why stamps_required cannot be lowered once cards exist', function () {
        $program = Program::factory()->for($this->business)->active()->create(['stamps_required' => 5]);
        Card::factory()->for($program)->create();

        $this->actingAs($this->owner)
            ->get(route('dashboard.programs.edit', $program))
            ->assertSee('1 card emis');

        $this->actingAs($this->owner)
            ->from(route('dashboard.programs.edit', $program))
            ->patch(route('dashboard.programs.update', $program), cafeWizardPayload(['stamps_required' => 4]))
            ->assertRedirect(route('dashboard.programs.edit', $program))
            ->assertSessionHasErrors('loyalty');

        expect($program->fresh()->stamps_required)->toBe(5);
    });
});

it('does not open the wizard for an archived program', function () {
    $program = Program::factory()->for($this->business)->create(['status' => ProgramStatus::Archived]);

    $this->actingAs($this->owner)
        ->get(route('dashboard.programs.edit', $program))
        ->assertRedirect(route('dashboard.programs.index'))
        ->assertSessionHasErrors('loyalty');
});

it('offers the actions allowed by the program status', function (ProgramStatus $status, array $visible, array $hidden) {
    Program::factory()->for($this->business)->create(['status' => $status]);

    $response = $this->actingAs($this->owner)->get(route('dashboard.programs.index'))->assertOk();

    foreach ($visible as $value) {
        $response->assertSee('name="status" value="'.$value.'"', false);
    }
    foreach ($hidden as $value) {
        $response->assertDontSee('name="status" value="'.$value.'"', false);
    }
})->with([
    // O ciornă cu carduri nu se poate șterge, dar se poate arhiva.
    'draft' => [ProgramStatus::Draft, ['active', 'archived'], ['paused']],
    'active' => [ProgramStatus::Active, ['paused', 'archived'], ['active']],
    'paused' => [ProgramStatus::Paused, ['active', 'archived'], ['paused']],
    'archived' => [ProgramStatus::Archived, [], ['active', 'paused', 'archived']],
]);

describe('actions', function () {
    it('changes the program status', function (ProgramStatus $from, string $to, ProgramStatus $expected) {
        $program = Program::factory()->for($this->business)->create(['status' => $from]);

        $this->actingAs($this->owner)
            ->post(route('dashboard.programs.status', $program), ['status' => $to])
            ->assertRedirect(route('dashboard.programs.index'))
            ->assertSessionHasNoErrors();

        expect($program->fresh()->status)->toBe($expected);
    })->with([
        'publish' => [ProgramStatus::Draft, 'active', ProgramStatus::Active],
        'pause' => [ProgramStatus::Active, 'paused', ProgramStatus::Paused],
        'resume' => [ProgramStatus::Paused, 'active', ProgramStatus::Active],
        'archive' => [ProgramStatus::Active, 'archived', ProgramStatus::Archived],
    ]);

    it('refuses an invalid status change with a clear message', function () {
        $program = Program::factory()->for($this->business)->create(['status' => ProgramStatus::Archived]);

        $this->actingAs($this->owner)
            ->from(route('dashboard.programs.index'))
            ->post(route('dashboard.programs.status', $program), ['status' => 'active'])
            ->assertRedirect(route('dashboard.programs.index'))
            ->assertSessionHasErrors('loyalty');

        expect($program->fresh()->status)->toBe(ProgramStatus::Archived);
    });

    it('validates the requested status', function () {
        $program = Program::factory()->for($this->business)->active()->create();

        $this->actingAs($this->owner)
            ->post(route('dashboard.programs.status', $program), ['status' => 'draft'])
            ->assertSessionHasErrors('status');
    });

    it('duplicates a program as a new draft and opens it', function () {
        $program = Program::factory()->for($this->business)->active()->create(['name' => 'Cafele fidele']);

        $response = $this->actingAs($this->owner)->post(route('dashboard.programs.duplicate', $program));

        $copy = Program::query()->where('name', 'Cafele fidele (copie)')->sole();
        $response->assertRedirect(route('dashboard.programs.edit', $copy));
        expect($copy->status)->toBe(ProgramStatus::Draft);
    });

    it('deletes a draft', function () {
        $program = Program::factory()->for($this->business)->create();

        $this->actingAs($this->owner)
            ->delete(route('dashboard.programs.destroy', $program))
            ->assertRedirect(route('dashboard.programs.index'));

        expect(Program::query()->count())->toBe(0);
    });

    it('refuses to delete an active program', function () {
        $program = Program::factory()->for($this->business)->active()->create();

        $this->actingAs($this->owner)
            ->from(route('dashboard.programs.index'))
            ->delete(route('dashboard.programs.destroy', $program))
            ->assertSessionHasErrors('loyalty');

        expect(Program::query()->count())->toBe(1);
    });
});
