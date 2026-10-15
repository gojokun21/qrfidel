<?php

namespace Database\Factories;

use App\Enums\RewardStatus;
use App\Models\Card;
use App\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reward>
 */
class RewardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'status' => RewardStatus::Available,
            'issued_at' => now(),
        ];
    }
}
