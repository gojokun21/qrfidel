<?php

namespace Database\Factories;

use App\Enums\BusinessStatus;
use App\Enums\BusinessType;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'type' => fake()->randomElement(BusinessType::cases()),
            'brand_color' => fake()->hexColor(),
            'timezone' => 'Europe/Bucharest',
            'status' => BusinessStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BusinessStatus::Suspended,
        ]);
    }
}
