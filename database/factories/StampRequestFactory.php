<?php

namespace Database\Factories;

use App\Enums\StampRequestStatus;
use App\Models\Card;
use App\Models\Location;
use App\Models\StampRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StampRequest>
 */
class StampRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            // Locația trebuie să fie a aceleiași afaceri ca și cardul.
            'location_id' => fn (array $attributes) => Location::factory()->create([
                'business_id' => Card::query()->withoutGlobalScopes()->whereKey($attributes['card_id'])->value('business_id'),
            ])->getKey(),
            'status' => StampRequestStatus::Pending,
            'quantity' => 1,
            'expires_at' => now()->addMinutes(10),
        ];
    }
}
