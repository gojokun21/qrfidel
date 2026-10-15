<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Business;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Staff,
            'token_hash' => Invitation::hashToken(Str::random(40)),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => Invitation::hashToken($token),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function revoked(): static
    {
        return $this->afterMaking(function (Invitation $invitation) {
            $invitation->revoked_at = now();
        });
    }

    public function accepted(): static
    {
        return $this->afterMaking(function (Invitation $invitation) {
            $invitation->accepted_at = now();
        });
    }
}
