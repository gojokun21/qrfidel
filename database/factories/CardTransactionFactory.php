<?php

namespace Database\Factories;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\CardTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Doar pentru teste și seed: în aplicație tranzacțiile le scriu serviciile din app/Services/Loyalty.
 *
 * @extends Factory<CardTransaction>
 */
class CardTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'type' => TransactionType::Stamp,
            'quantity' => 1,
            'source' => TransactionSource::Scan,
            'idempotency_key' => (string) Str::ulid(),
        ];
    }

    public function void(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Void,
            'quantity' => -1,
            'source' => TransactionSource::Manual,
        ]);
    }
}
