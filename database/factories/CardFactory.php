<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Customer;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id se derivă din program (BelongsToBusiness).
 *
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory()->active(),
            'customer_id' => Customer::factory(),
        ];
    }
}
