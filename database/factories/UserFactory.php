<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function platformAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_platform_admin' => true,
        ]);
    }

    /**
     * Adaugă userul în afacere cu rolul dat (se poate înlănțui pentru mai multe afaceri).
     */
    public function withRole(Business $business, Role $role, ?Location $location = null): static
    {
        return $this->afterCreating(function (User $user) use ($business, $role, $location) {
            $user->businesses()->attach($business, [
                'role' => $role,
                'location_id' => $location?->getKey(),
            ]);
        });
    }
}
