<?php

namespace Database\Factories;

use App\Enums\ProgramStatus;
use App\Enums\ProgramType;
use App\Enums\StampIcon;
use App\Models\Business;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => '5 cafele = 1 gratis',
            'type' => ProgramType::Stamps,
            'stamps_required' => 5,
            'reward_title' => 'Cafea gratis',
            'reward_description' => null,
            'max_stamps_per_visit' => 1,
            'cooldown_minutes' => 30,
            'daily_limit' => 1,
            'stamps_expire_days' => null,
            'reward_expire_days' => null,
            'rollover_extra' => false,
            'color' => null,
            'stamp_icon' => StampIcon::Coffee,
            'status' => ProgramStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProgramStatus::Active,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProgramStatus::Paused,
        ]);
    }
}
