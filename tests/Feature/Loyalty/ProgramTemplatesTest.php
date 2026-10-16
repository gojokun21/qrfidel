<?php

use App\Enums\BusinessType;
use App\Enums\StampIcon;
use App\Models\Business;
use App\Models\Program;
use App\Services\Loyalty\ProgramTemplate;
use App\Services\Loyalty\ProgramTemplates;

it('has one template for every business type', function (BusinessType $type) {
    $template = ProgramTemplates::for($type);

    expect($template)->toBeInstanceOf(ProgramTemplate::class)
        ->and($template->key)->toBe($type->value)
        ->and(ProgramTemplates::keys())->toContain($template->key);
})->with(BusinessType::cases());

it('uses the presets from SDD §7', function () {
    expect(ProgramTemplates::for(BusinessType::Cafe)->attributes)->toMatchArray([
        'stamps_required' => 5,
        'cooldown_minutes' => 30,
        'daily_limit' => 1,
        'stamp_icon' => StampIcon::Coffee->value,
    ])->and(ProgramTemplates::for(BusinessType::Restaurant)->attributes)->toMatchArray([
        'stamps_required' => 8,
        'reward_title' => 'Desert gratis',
    ])->and(ProgramTemplates::for(BusinessType::Carwash)->attributes)->toMatchArray([
        'stamps_required' => 10,
        'daily_limit' => 1,
        'stamp_icon' => StampIcon::Car->value,
    ]);
});

it('produces templates that satisfy the database rules', function (BusinessType $type) {
    $program = Program::query()->create([
        'business_id' => Business::factory()->create()->id,
        ...ProgramTemplates::for($type)->attributes,
    ]);

    expect($program->exists)->toBeTrue()
        ->and($program->max_stamps_per_visit)->toBeLessThanOrEqual($program->stamps_required);
})->with(BusinessType::cases());

it('lists the template of the business type first', function () {
    $business = Business::factory()->make(['type' => BusinessType::Carwash]);

    $keys = array_map(fn (ProgramTemplate $template) => $template->key, ProgramTemplates::orderedFor($business));

    expect($keys[0])->toBe('carwash')
        ->and($keys)->toHaveCount(count(BusinessType::cases()));
});
