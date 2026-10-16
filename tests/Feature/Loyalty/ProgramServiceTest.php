<?php

use App\Enums\BusinessType;
use App\Enums\ProgramStatus;
use App\Enums\Role;
use App\Enums\StampIcon;
use App\Exceptions\ProgramRuleViolation;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Card;
use App\Models\Program;
use App\Models\User;
use App\Services\Loyalty\ProgramService;
use App\Services\Loyalty\ProgramTemplates;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->business = Business::factory()->create(['type' => BusinessType::Cafe]);
    $this->owner = User::factory()->withRole($this->business, Role::Owner)->create();
    $this->service = app(ProgramService::class);
});

function programData(array $overrides = []): array
{
    return [...ProgramTemplates::for(BusinessType::Cafe)->attributes, 'color' => '#7c2d12', ...$overrides];
}

describe('create', function () {
    it('creates a draft program from the cafe template', function () {
        $program = $this->service->create($this->owner, $this->business, programData(), template: 'cafe');

        expect($program->business_id)->toBe($this->business->id)
            ->and($program->status)->toBe(ProgramStatus::Draft)
            ->and($program->stamps_required)->toBe(5)
            ->and($program->cooldown_minutes)->toBe(30)
            ->and($program->daily_limit)->toBe(1)
            ->and($program->stamp_icon)->toBe(StampIcon::Coffee)
            ->and($program->color)->toBe('#7c2d12');

        $audit = AuditLog::query()->where('action', 'program.created')->sole();
        expect($audit->payload)->toMatchArray(['template' => 'cafe', 'status' => 'draft']);
    });

    it('publishes the program right away when asked', function () {
        $program = $this->service->create($this->owner, $this->business, programData(), publish: true);

        expect($program->status)->toBe(ProgramStatus::Active);
    });

    it('ignores attributes outside the program fields', function () {
        $foreign = Business::factory()->create();

        $program = $this->service->create($this->owner, $this->business, programData([
            'business_id' => $foreign->id,
            'status' => 'active',
        ]));

        expect($program->business_id)->toBe($this->business->id)
            ->and($program->status)->toBe(ProgramStatus::Draft);
    });

    it('refuses non-owners', function (Role $role) {
        $user = User::factory()->withRole($this->business, $role)->create();

        $this->service->create($user, $this->business, programData());
    })->throws(AuthorizationException::class)->with([Role::Manager, Role::Staff]);

    it('refuses the owner of another business', function () {
        $this->service->create($this->owner, Business::factory()->create(), programData());
    })->throws(AuthorizationException::class);

    it('refuses to publish for a suspended business', function () {
        $suspended = Business::factory()->suspended()->create();
        $owner = User::factory()->withRole($suspended, Role::Owner)->create();

        $this->service->create($owner, $suspended, programData(), publish: true);
    })->throws(ProgramRuleViolation::class);
});

describe('update', function () {
    it('updates the rules and records what changed', function () {
        $program = Program::factory()->for($this->business)->active()->create(['stamps_required' => 5]);

        $this->service->update($this->owner, $program, programData(['stamps_required' => 6, 'reward_title' => 'Cafea mare']));

        expect($program->fresh()->stamps_required)->toBe(6)
            ->and($program->fresh()->reward_title)->toBe('Cafea mare');

        $audit = AuditLog::query()->where('action', 'program.updated')->sole();
        expect($audit->payload['changed'])->toContain('stamps_required', 'reward_title');
    });

    it('refuses to lower stamps_required once cards were issued', function (ProgramStatus $status) {
        $program = Program::factory()->for($this->business)->create(['stamps_required' => 5, 'status' => $status]);
        Card::factory()->for($program)->create();

        expect(fn () => $this->service->update($this->owner, $program, programData(['stamps_required' => 4])))
            ->toThrow(ProgramRuleViolation::class, 'nu poți micșora numărul de ștampile');

        expect($program->fresh()->stamps_required)->toBe(5);
    })->with([ProgramStatus::Active, ProgramStatus::Paused]);

    it('allows raising stamps_required once cards were issued', function () {
        $program = Program::factory()->for($this->business)->active()->create(['stamps_required' => 5]);
        Card::factory()->for($program)->create();

        $this->service->update($this->owner, $program, programData(['stamps_required' => 7]));

        expect($program->fresh()->stamps_required)->toBe(7);
    });

    it('allows lowering stamps_required while no card exists', function () {
        $program = Program::factory()->for($this->business)->active()->create(['stamps_required' => 8]);

        $this->service->update($this->owner, $program, programData(['stamps_required' => 5]));

        expect($program->fresh()->stamps_required)->toBe(5);
    });

    it('refuses to edit an archived program', function () {
        $program = Program::factory()->for($this->business)->create(['status' => ProgramStatus::Archived]);

        $this->service->update($this->owner, $program, programData());
    })->throws(ProgramRuleViolation::class);

    it('never changes the status through an update', function () {
        $program = Program::factory()->for($this->business)->create();

        $this->service->update($this->owner, $program, programData(['status' => 'active']));

        expect($program->fresh()->status)->toBe(ProgramStatus::Draft);
    });

    it('refuses non-owners', function () {
        $program = Program::factory()->for($this->business)->create();
        $manager = User::factory()->withRole($this->business, Role::Manager)->create();

        $this->service->update($manager, $program, programData());
    })->throws(AuthorizationException::class);
});

describe('status', function () {
    it('allows the lifecycle transitions', function (ProgramStatus $from, ProgramStatus $to) {
        $program = Program::factory()->for($this->business)->create(['status' => $from]);

        $this->service->changeStatus($this->owner, $program, $to);

        expect($program->fresh()->status)->toBe($to);

        $audit = AuditLog::query()->where('action', 'program.status_changed')->sole();
        expect($audit->payload)->toEqual(['from' => $from->value, 'to' => $to->value]);
    })->with([
        'publish' => [ProgramStatus::Draft, ProgramStatus::Active],
        'archive draft' => [ProgramStatus::Draft, ProgramStatus::Archived],
        'pause' => [ProgramStatus::Active, ProgramStatus::Paused],
        'archive active' => [ProgramStatus::Active, ProgramStatus::Archived],
        'resume' => [ProgramStatus::Paused, ProgramStatus::Active],
        'archive paused' => [ProgramStatus::Paused, ProgramStatus::Archived],
    ]);

    it('refuses the other transitions', function (ProgramStatus $from, ProgramStatus $to) {
        $program = Program::factory()->for($this->business)->create(['status' => $from]);

        expect(fn () => $this->service->changeStatus($this->owner, $program, $to))->toThrow(ProgramRuleViolation::class);
        expect($program->fresh()->status)->toBe($from);
    })->with([
        'draft to paused' => [ProgramStatus::Draft, ProgramStatus::Paused],
        'back to draft' => [ProgramStatus::Active, ProgramStatus::Draft],
        'same status' => [ProgramStatus::Active, ProgramStatus::Active],
        'archived is final (active)' => [ProgramStatus::Archived, ProgramStatus::Active],
        'archived is final (paused)' => [ProgramStatus::Archived, ProgramStatus::Paused],
    ]);

    it('refuses to activate a program of a suspended business', function () {
        $suspended = Business::factory()->suspended()->create();
        $owner = User::factory()->withRole($suspended, Role::Owner)->create();
        $program = Program::factory()->for($suspended)->paused()->create();

        $this->service->changeStatus($owner, $program, ProgramStatus::Active);
    })->throws(ProgramRuleViolation::class);

    it('refuses non-owners', function () {
        $program = Program::factory()->for($this->business)->active()->create();
        $manager = User::factory()->withRole($this->business, Role::Manager)->create();

        $this->service->changeStatus($manager, $program, ProgramStatus::Paused);
    })->throws(AuthorizationException::class);
});

describe('duplicate', function () {
    it('copies the rules and appearance into a new draft', function () {
        $program = Program::factory()->for($this->business)->active()->create([
            'name' => '5 cafele = 1 gratis',
            'stamps_required' => 5,
            'color' => '#123456',
            'stamp_icon' => StampIcon::Heart,
            'terms' => 'Doar în zilele lucrătoare.',
        ]);
        Card::factory()->for($program)->create();

        $copy = $this->service->duplicate($this->owner, $program);

        expect($copy->id)->not->toBe($program->id)
            ->and($copy->status)->toBe(ProgramStatus::Draft)
            ->and($copy->name)->toBe('5 cafele = 1 gratis (copie)')
            ->and($copy->stamps_required)->toBe(5)
            ->and($copy->color)->toBe('#123456')
            ->and($copy->stamp_icon)->toBe(StampIcon::Heart)
            ->and($copy->terms)->toBe('Doar în zilele lucrătoare.')
            ->and($copy->cards()->count())->toBe(0)
            ->and(AuditLog::query()->where('action', 'program.duplicated')->exists())->toBeTrue();
    });

    it('keeps the copy name within the length limit', function () {
        $program = Program::factory()->for($this->business)->create(['name' => str_repeat('a', 120)]);

        expect(mb_strlen($this->service->duplicate($this->owner, $program)->name))->toBeLessThanOrEqual(120);
    });
});

describe('delete', function () {
    it('deletes a draft without cards', function () {
        $program = Program::factory()->for($this->business)->create();

        $this->service->delete($this->owner, $program);

        expect(Program::query()->whereKey($program->id)->exists())->toBeFalse()
            ->and(AuditLog::query()->where('action', 'program.deleted')->exists())->toBeTrue();
    });

    it('refuses to delete a program that is not a draft', function (ProgramStatus $status) {
        $program = Program::factory()->for($this->business)->create(['status' => $status]);

        expect(fn () => $this->service->delete($this->owner, $program))->toThrow(ProgramRuleViolation::class);
        expect(Program::query()->whereKey($program->id)->exists())->toBeTrue();
    })->with([ProgramStatus::Active, ProgramStatus::Paused, ProgramStatus::Archived]);

    it('refuses to delete a draft that has cards', function () {
        $program = Program::factory()->for($this->business)->create();
        Card::factory()->for($program)->create();

        $this->service->delete($this->owner, $program);
    })->throws(ProgramRuleViolation::class);
});
